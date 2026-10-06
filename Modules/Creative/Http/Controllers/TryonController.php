<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Events\TryonReviewed;
use Modules\Creative\Http\Controllers\Concerns\HandlesCreativeReview;
use Modules\Creative\Http\Requests\GenerateCloseupsRequest;
use Modules\Creative\Http\Requests\GenerateTryonRequest;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\GarmentLabel;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\StylistOption;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\TryOnDriverResolver;
use Modules\Creative\Services\Enhancement\GarmentDetailClassifierContract;
use Modules\Creative\Services\FalBalanceService;
use Modules\Creative\Services\GeminiSpendService;
use Modules\Creative\Services\Mockup\MockupRerunService;
use Modules\Creative\Services\Mockup\MockupSettings;
use Modules\Creative\Services\ProductCloseupService;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Creative\Services\ReviewNotifier;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

class TryonController extends Controller
{
    use HandlesCreativeReview;

    /**
     * İlk sayfa yükünde gösterilen ürün adedi — tüm ürün tablosunu her
     * seferinde çekmek yerine (katalog büyüdükçe yavaşlayan/ağırlaşan bir
     * yaklaşım), yalnız ilk N ürün gelir; kalanına {@see products()} arama
     * uç noktasıyla erişilir.
     */
    private const PRODUCT_PAGE_SIZE = 60;

    public function index(GeminiSpendService $geminiSpend, TryOnDriverResolver $tryOnDrivers, MockupSettings $mockupSettings): Response
    {
        $productsTotal = Product::query()->count();

        $products = $this->mapProducts(
            $this->searchProducts(null)->take(self::PRODUCT_PAGE_SIZE)->get(['id', 'name', 'category_id']),
        );

        // Kimliği hazır + referans görseli olan VE ONAYLI mankenler giydirmeye uygundur —
        // onaylanmamış bir kimlikle giydirme üretilirse, onay reddedilirse tüm giydirmeler
        // de baştan üretilmesi gerekir.
        $mannequins = Mannequin::query()
            ->where('status', Mannequin::STATUS_READY)
            ->where('review_status', Mannequin::REVIEW_APPROVED)
            ->whereNotNull('reference_image_path')
            ->orderBy('name')
            ->get()
            ->map(fn (Mannequin $m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'reference_url' => $this->url($m->reference_image_path),
            ]);

        // Bağımsız poz kütüphanesinden hazır pozlar.
        $poses = Pose::query()
            ->where('status', Pose::STATUS_READY)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Pose $p) => [
                'id'          => $p->id,
                'label'       => $p->label,
                'preview_url' => $this->url($p->preview_image_path),
            ]);

        // Son giydirmeler galerisi (global + ürüne özel) VE fal.ai bakiyesi artık ilk
        // sayfa yükünü ağırlaştırmasın diye burada HİÇ hesaplanmaz — sayfa açılır
        // açılmaz {@see results()} / {@see falBalance()} uç noktalarından axios ile
        // ayrıca (bloklamadan) çekilir.
        return Inertia::render('Creative::CreativeTryon', [
            'products'         => $products,
            'productsTotal'    => $productsTotal,
            'mannequins'       => $mannequins,
            'poses'            => $poses,
            'rejectionReasons' => $this->rejectionReasonGroups('tryon'),
            'geminiSpend'      => $geminiSpend->currentMonthSpend(),
            // Onaylı giydirmeden üretilebilecek yakın plan kadrajları (anahtar => etiket).
            'closeupShots'     => ProductCloseupService::shotOptions(),
            // Başlıktaki Gemini / fal.ai seçici: varsayılan + anahtarı tanımlı olanlar.
            'tryonDriver'      => [
                'default'   => $tryOnDrivers->defaultDriver(),
                'available' => $tryOnDrivers->availability(),
            ],
            // Mokap (Faz 4): günlük kalan hak + varsayılan manken tanımlı mı (Creative ayarlar › Mokap).
            'mockup'           => [
                'remaining_today' => $mockupSettings->remainingToday(),
                'daily_limit'     => $mockupSettings->dailyLimit(),
                'has_mannequins'  => $mockupSettings->mannequinPool()->isNotEmpty(),
                'batch'           => (bool) config('creative.mockup.batch', true),
            ],
        ]);
    }

    /**
     * fal.ai kredi bakiyesi (axios ile çağrılır, Inertia sayfası değil) —
     * {@see index()} artık bunu hiç hesaplamaz; sayfa açılır açılmaz ve
     * ardından her 10 dakikada bir buradan ayrıca (bloklamadan) çekilir.
     */
    public function falBalance(FalBalanceService $falBalance): JsonResponse
    {
        return response()->json(['data' => $falBalance->current()]);
    }

    /**
     * Ürün seçici arama/sayfalama uç noktası (axios ile çağrılır, Inertia sayfası
     * değil) — {@see index()}'in yalnız ilk {@see PRODUCT_PAGE_SIZE} ürünü
     * göndermesinin karşılığı: arama kutusuna yazınca (q) ya da "Daha fazla
     * yükle"ye basınca (offset) kalan katalog buradan gelir.
     */
    public function products(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim()->value() ?: null;
        $offset = max(0, $request->integer('offset'));

        $products = $this->mapProducts(
            $this->searchProducts($q)->skip($offset)->take(self::PRODUCT_PAGE_SIZE)->get(['id', 'name', 'category_id']),
        );

        return response()->json(['data' => $products]);
    }

    /**
     * Son giydirmeler galerisi (axios ile çağrılır) — product_id verilmezse
     * TÜM ürünlerin son 60 kaydı (global akış: ör. yalnız 'creative.approve'
     * yetkisiyle onay bekleyen her ürünü inceleyen, sihirbazı hiç görmeyen bir
     * onaylayıcı için), verilirse o ürünün TÜM geçmişi (200 kayda kadar) döner.
     * {@see index()} artık bunu hiç hesaplamaz — ilk sayfa yükünü ağırlaştırmasın
     * diye sayfa açıldıktan sonra buradan ayrıca (bloklamadan) çekilir.
     */
    public function results(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['nullable', 'integer', 'exists:products,id']]);

        $query = $this->tryonResultsQuery()->latest();

        if (! empty($data['product_id'])) {
            $query->where('product_id', $data['product_id'])->limit(200);
        } else {
            $query->limit(60);
        }

        return response()->json(['data' => $this->mapTryonResults($query->get())]);
    }

    private function tryonResultsQuery(): Builder
    {
        return TryonResult::query()->with([
            'product:id,name', 'mannequin:id,name', 'pose:id,label',
            'productImage:id,path,is_cover', 'creator:id,name', 'reviewer:id,name', 'mockupBase.outfit',
            'reviewChats' => fn ($q) => $q->orderBy('created_at')->with('user:id,name'),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,TryonResult>  $results
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function mapTryonResults(\Illuminate\Support\Collection $results)
    {
        $labels = $results->contains(fn ($r) => $r->mockupBase?->outfit) ? StylistOption::query()->get(['kind', 'key', 'label_tr'])
            ->mapWithKeys(fn ($o) => ["{$o->kind}.{$o->key}" => $o->label_tr])->all() : [];

        $rerun = app(MockupRerunService::class);

        return $results->map(fn (TryonResult $r) => [
            'id'              => $r->id,
            'status'          => $r->status,
            'tryon_driver'    => $r->tryon_driver,
            'tryon_model'     => $r->tryon_model,
            'error'           => $r->error,
            'product_id'      => $r->product_id,
            'product_name'    => $r->product?->name,
            'mannequin_name'  => $r->mannequin?->name,
            'pose_id'         => $r->pose_id,
            'pose_label'      => $r->pose?->label,
            // 'full' = tam boy giydirme; diğerleri yakın plan kadrajı (bkz. ProductCloseupService).
            'shot'            => $r->shot ?? TryonResult::SHOT_FULL,
            'pipeline'        => $r->pipeline ?? TryonResult::PIPELINE_LEGACY,
            // Mokap renk seti: giydirilen ürün rengi ('' = genel set, bütün renkler).
            'color'           => (string) $r->color,
            // Faz 2 otomatik kontrol özeti (yalnız mokap): durum + deneme sayısı + onaycıya Türkçe bulgular.
            'qa'              => isset($r->qa['status']) ? [
                'status'   => $r->qa['status'],
                'attempts' => (int) ($r->qa['attempts'] ?? 1),
                'issues'   => collect([...((array) ($r->qa['fidelity']['issues'] ?? [])), ...((array) ($r->qa['base_issues'] ?? []))])
                    ->pluck('detail_tr')->filter()->values()->all(),
            ] : null,
            // Mokap kombin özeti (Faz 3/4): parçalar (Türkçe etiket), stilistin gerekçesi + uyarıları.
            'outfit'          => ($o = $r->mockupBase?->outfit) ? [
                'pieces'     => collect((array) $o->pieces)->reject(fn ($k) => $k === 'none')
                    ->map(fn ($k, $kind) => $labels["{$kind}.{$k}"] ?? $k)->values()->all(),
                'reasoning'  => $o->reasoning_tr,
                'warnings'   => array_values(array_filter([$o->meta['mannequin_note'] ?? null,
                    ($o->meta['colors_missing'] ?? []) ? 'Yalın fotoğrafı olmayan renkler (genel setle gösterilir): ' . implode(', ', $o->meta['colors_missing']) : null,
                    ...((array) ($o->meta['stylist_warnings'] ?? []))])),
                'confidence' => $o->meta['stylist_confidence'] ?? null,
                // Beden grubu (sitede bu bedenler seçilince bu mankenin görselleri gösterilecek).
                'sizes'      => isset($o->meta['ages'][0]) ? ($o->meta['ages'][0] === $o->meta['ages'][1]
                    ? "{$o->meta['ages'][0]} Yaş" : "{$o->meta['ages'][0]}–{$o->meta['ages'][1]} Yaş") : null,
            ] : null,
            'shot_label'      => $r->isCloseup() ? (ProductCloseupService::shotOptions()[$r->shot] ?? $r->shot) : null,
            'source_result_id' => $r->source_result_id,
            // Onaydan önce henüz product_images satırı yok; staged (bekleyen) önizleme gösterilir.
            // Dosya adı result id'sine sabit (onmodel_staged_{id}.webp) ve nginx statik
            // varlıkları 10 yıl cache'liyor (bkz. `expires max`) — aynı satır yeniden
            // üretilince (regenerate) tarayıcı eski baytları göstermeye devam eder.
            // updated_at'a bağlı ?v= parametresi URL'i her üretimde değiştirip cache'i kırar.
            'image_url'       => $this->versionedUrl($r->productImage?->url ?? Media::url($r->staged_image_path), $r->updated_at),
            'is_cover'        => (bool) $r->productImage?->is_cover,
            'created_at'      => $r->created_at?->toDateTimeString(),
            'created_by'      => $r->created_by,
            'creator_name'    => $r->creator?->name,
            'review_status'   => $r->review_status,
            'review_note'     => $r->review_note,
            'review_tags'     => $r->review_tags ?? [],
            'reviewer_name'   => $r->reviewer?->name,
            'can_review'      => auth()->user()?->can('creative.approve')
                && $r->review_status === TryonResult::REVIEW_PENDING,
            'is_own'          => $r->created_by === auth()->id(),
            'can_chat'        => $r->review_status === TryonResult::REVIEW_REJECTED
                && ($r->created_by === auth()->id() || auth()->user()?->can('creative.approve')),
            'review_chats'    => $r->reviewChats->map(fn ($c) => [
                'id'         => $c->id,
                'role'       => $c->role,
                'content'    => $c->content,
                'user_name'  => $c->user?->name,
                'created_at' => $c->created_at?->toDateTimeString(),
            ]),
            'chat_suggestion' => $r->meta['chat_suggested_instruction'] ?? null,
            // Faz 5: reddedilen mokap → hangi aşamadan, tahmini kaça yeniden üretilir.
            'rerun'           => $r->isMockup() && $r->review_status === TryonResult::REVIEW_REJECTED && $r->mockupBase
                ? $rerun->plan($r) : null,
        ]);
    }

    private function searchProducts(?string $q): Builder
    {
        return Product::query()
            ->when($q, fn ($query) => $query->where('name', 'ilike', "%{$q}%"))
            ->with(['images' => fn ($iq) => $iq->reorder()->orderByDesc('is_cover')->orderBy('sort_order'), 'category.parent'])
            ->orderBy('name');
    }

    /**
     * Giysi kaynağı ProductOnModelService::pickGarmentSrc ile aynı mantıkta seçilir:
     * önce kapak, yoksa ilk görsel. Görseli olmayan ürün giydirilemez (has_garment=false).
     *
     * @param  \Illuminate\Support\Collection<int,Product>  $products
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function mapProducts(\Illuminate\Support\Collection $products)
    {
        $settings = app(MockupSettings::class);

        return $products->map(function (Product $p) use ($settings) {
            $garment = $p->images->first();

            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'cover'       => $garment?->url,
                'has_garment' => $garment !== null,
                // Mokap bölgesi (Creative ayarlar › Mokap); null = mokap dışı kategori (eski hat).
                'mockup_region' => $settings->regionFor($p->category)['region'] ?? null,
            ];
        });
    }

    public function store(GenerateTryonRequest $request, ProductOnModelService $service): RedirectResponse
    {
        $product   = Product::with('images')->findOrFail($request->validated('product_id'));
        $mannequin = Mannequin::findOrFail($request->validated('mannequin_id'));

        // Giydirme normalde ürünün kendi fotoğrafını giysi olarak kullanır. Ürünün
        // fotoğrafı yoksa (ya da kullanıcı farklı bir görsel giydirmek isterse) ekrandan
        // ayrıca yüklenen görsel devreye girer; bu görsel kalıcı olarak ürüne eklenmez,
        // sadece bu üretim için kullanılır.
        $garmentImagePath = null;
        if ($request->hasFile('garment_image')) {
            $garmentImagePath = $request->file('garment_image')->store('creative/tryon_garments', config('creative.disk', 'public'));
        } else {
            $garment = $product->images->firstWhere('is_cover', true) ?? $product->images->first();
            if (! $garment) {
                throw ValidationException::withMessages([
                    'garment_image' => __('creative::messages.tryon.no_garment_image'),
                ]);
            }
        }

        // Opsiyonel detay görselleri (arkadan/yandan/dikiş vb.) — her biri diske
        // yazılır, path+label çifti olarak sonuca (meta.garment_extras) taşınır.
        $garmentExtras = [];
        foreach ((array) $request->validated('garment_details', []) as $detail) {
            $file = $detail['image'] ?? null;
            if (! $file) {
                continue;
            }
            $garmentExtras[] = [
                'path'            => $file->store('creative/tryon_garments', config('creative.disk', 'public')),
                'label'           => trim((string) ($detail['label'] ?? '')) ?: null,
                // Yerel CLIP sınıflandırıcının bu görsel için bulduğu tüm adaylar —
                // yalnız raporlama/detay sayfası için saklanır, giydirme pipeline'ını
                // etkilemez (bkz. GeminiTryOnPromptBuilder yalnız 'label'ı kullanır).
                'detected_labels' => $detail['detected_labels'] ?? [],
            ];
        }

        $queued = $service->queue($product, $mannequin, $request->validated('pose_ids'), auth()->id(), $garmentImagePath, $garmentExtras, $request->validated('tryon_driver'));

        if ($queued->isEmpty()) {
            return back()->with('error', __('creative::messages.tryon.no_pose_ready'));
        }

        foreach ($queued as $result) {
            GenerateOnModelJob::dispatch($result->id, $result->generation_token);
        }

        return back()->with('success', __('creative::messages.tryon.queued', ['count' => $queued->count()]));
    }

    /**
     * Onaylı bir tam boy giydirmeden seçili kadrajlarda yakın plan / detay çekimleri
     * kuyruğa alır (bkz. ProductCloseupService). Sonuçlar galeride aynı onay akışıyla görünür.
     */
    public function closeups(TryonResult $result, GenerateCloseupsRequest $request, ProductCloseupService $service): RedirectResponse
    {
        try {
            $queued = $service->queue($result, $request->validated('shots'), auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        foreach ($queued as $closeup) {
            GenerateOnModelJob::dispatch($closeup->id, $closeup->generation_token);
        }

        return back()->with('success', __('creative::messages.closeup.queued', ['count' => $queued->count()]));
    }

    /**
     * Kullanıcı bir detay görseli seçer seçmez çağrılır: görselin ne gösterdiğini
     * (yaka/düğme/kol ucu vb.) yerel bir ML modeliyle tahmin edip ÖNERİ olarak
     * döndürür. Hiçbir şey kalıcı olarak saklanmaz; sonuç yalnız Vue formundaki
     * etiket alanını boşsa doldurmak için kullanılır, kullanıcı serbestçe
     * düzenleyebilir. Sınıflandırma kapalıysa ya da başarısız olursa boş liste
     * döner — bu uç nokta asla giydirme akışını etkilemez.
     */
    public function classifyDetail(Request $request, GarmentDetailClassifierContract $classifier): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $request->file('image')->getRealPath();
        $labels = $classifier->classify([$path])[$path] ?? [];

        return response()->json(['labels' => $labels]);
    }

    /**
     * Tek bir giydirme sonucunun detay sayfası: hangi model/sürücüyle ne kadar
     * sürede üretildiği ve her detay görseli için yerel sınıflandırıcının bulduğu
     * TÜM aday etiketler (yalnız kullanılan değil) — sistemin geliştirilmesi
     * (öneri kalitesi/üretim performansı) amacıyla raporlama için.
     */
    public function show(TryonResult $result): Response
    {
        $result->load(['product:id,name', 'mannequin:id,name', 'pose:id,label', 'productImage:id,path,is_cover', 'creator:id,name', 'reviewer:id,name', 'garmentScan']);

        $garmentExtras = collect((array) ($result->meta['garment_extras'] ?? []))
            ->map(fn (array $extra) => [
                'image_url'       => $this->url($extra['path'] ?? null),
                'label'           => $extra['label'] ?? null,
                'detected_labels' => $extra['detected_labels'] ?? [],
            ])
            ->values();

        // Otomatik parça tespiti (yaka/cep/etek vb. bbox) — raporlama şartı: hangi
        // model neyi nerede bulmuş göster. Taranmadıysa ya da model henüz eğitilmediyse
        // (model_version='null') detections boş gelir, Vue tarafı boş-durum gösterir.
        // Raporlama sayfası TÜM analiz alanlarını (düşük confidence dahil) gösterir —
        // try-on prompt'una giden filtrelenmiş hale (GarmentIdentityRuleEngine) bakmaz,
        // burada amaç şeffaflık/QA.
        $garmentScan = $result->garmentScan;
        $scanPayload = $garmentScan ? [
            'id'               => $garmentScan->id,
            'image_url'        => $this->url($garmentScan->source_path),
            'model_version'    => $garmentScan->model_version,
            'status'           => $garmentScan->status,
            'identity_summary' => $garmentScan->identity_summary,
            'detections'       => $this->detectionsWithPriority($garmentScan->detections ?? []),
        ] : null;

        return Inertia::render('Creative::CreativeTryonDetail', [
            'result' => [
                'id'                     => $result->id,
                'status'                 => $result->status,
                'error'                  => $result->error,
                'product_name'           => $result->product?->name,
                'mannequin_name'         => $result->mannequin?->name,
                'pose_label'             => $result->pose?->label,
                'shot_label'             => $result->isCloseup() ? (ProductCloseupService::shotOptions()[$result->shot] ?? $result->shot) : null,
                'tryon_driver'           => $result->tryon_driver,
                'tryon_model'            => $result->tryon_model,
                'generation_duration_ms' => $result->generation_duration_ms,
                'image_url'              => $this->versionedUrl($result->productImage?->url ?? Media::url($result->staged_image_path), $result->updated_at),
                'garment_image_url'      => $this->url($result->garment_image_path),
                'is_cover'               => (bool) $result->productImage?->is_cover,
                'created_at'             => $result->created_at?->toDateTimeString(),
                'creator_name'           => $result->creator?->name,
                'review_status'          => $result->review_status,
                'review_note'            => $result->review_note,
                'reviewer_name'          => $result->reviewer?->name,
                'reviewed_at'            => $result->reviewed_at?->toDateTimeString(),
                'color_audit'            => $result->meta['color_audit'] ?? null,
            ],
            'garmentExtras' => $garmentExtras,
            'garmentScan'   => $scanPayload,
        ]);
    }

    public function approve(TryonResult $result, ProductOnModelService $service, ReviewNotifier $notifier): RedirectResponse
    {
        if ($result->review_status !== TryonResult::REVIEW_PENDING) {
            return back()->with('error', __('creative::messages.tryon.not_pending'));
        }

        $service->publish($result);

        $result->update([
            'review_status' => TryonResult::REVIEW_APPROVED,
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        event(new TryonReviewed(
            resultId: $result->id,
            generationToken: $result->generation_token,
            verdict: $result->review_status,
            reviewTags: $result->review_tags,
            reviewNote: $result->review_note,
            reviewerId: $result->reviewed_by,
            reviewedAt: $result->reviewed_at,
        ));

        $notifier->notifyDecision($result, true);

        return back()->with('success', __('creative::messages.tryon.approved'));
    }

    public function reject(TryonResult $result, Request $request, ReviewNotifier $notifier): RedirectResponse
    {
        if ($result->review_status !== TryonResult::REVIEW_PENDING) {
            return back()->with('error', __('creative::messages.tryon.not_pending'));
        }

        $review = $this->validatedReview($request);

        $result->update([
            'review_status' => TryonResult::REVIEW_REJECTED,
            'review_note'   => $review['note'],
            'review_tags'   => $review['tags'],
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        event(new TryonReviewed(
            resultId: $result->id,
            generationToken: $result->generation_token,
            verdict: $result->review_status,
            reviewTags: $result->review_tags,
            reviewNote: $result->review_note,
            reviewerId: $result->reviewed_by,
            reviewedAt: $result->reviewed_at,
        ));

        $notifier->notifyDecision($result, false);

        return back()->with('success', __('creative::messages.tryon.rejected'));
    }

    /**
     * Giydirme çıktısını (üretilen ürün görselini) ürünün kapağı yapar.
     */
    public function setCover(TryonResult $result): RedirectResponse
    {
        $result->loadMissing('productImage');
        $image = $result->productImage;

        if ($result->status !== TryonResult::STATUS_DONE || ! $image) {
            return back()->with('error', __('creative::messages.tryon.cannot_be_cover'));
        }

        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->update(['is_cover' => false]);

            $image->update(['is_cover' => true]);
        });

        return back()->with('success', __('creative::messages.tryon.cover_updated'));
    }

    /**
     * Giydirme sonucunu, ürettiği ürün görselini (onaylandıysa) ve staged
     * (onaylanmamış) dosyayı siler.
     */
    public function destroyResult(TryonResult $result, ProductOnModelService $service): RedirectResponse
    {
        $service->destroy($result);

        return back()->with('success', __('creative::messages.tryon.deleted'));
    }

    /**
     * Her tespite {@see GarmentLabel}'in taban önceliğini + crop görselinin
     * URL'ini ekler — Vue tarafı bunları rozet/thumbnail olarak gösterir.
     *
     * @param  array<int,array<string,mixed>>  $detections
     * @return array<int,array<string,mixed>>
     */
    private function detectionsWithPriority(array $detections): array
    {
        if ($detections === []) {
            return [];
        }

        $labelKeys = collect($detections)->pluck('label_key')->filter()->unique();
        $labelsByKey = GarmentLabel::query()
            ->whereIn('key', $labelKeys)
            ->get()
            ->keyBy('key');

        return collect($detections)
            ->map(function (array $d) use ($labelsByKey) {
                $label = $labelsByKey->get($d['label_key'] ?? null);
                $d['default_priority'] = $label?->default_priority;
                $d['crop_image_url']   = $this->url($d['crop_path'] ?? null);

                return $d;
            })
            ->all();
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }

    /**
     * onmodel_staged_{id}.webp gibi SABİT dosya adlarını nginx'in `expires max`
     * (10 yıllık) statik cache'ine karşı korur: her üretimde updated_at değişir,
     * dolayısıyla ?v= parametresi de değişir ve tarayıcı yeni bir URL olarak
     * görüp yeniden ister. Dosya kendisi asla querystring almaz (disk yazımı
     * $r->staged_image_path ile aynı kalır), bu yalnız HTTP katmanında eklenir.
     */
    private function versionedUrl(?string $url, ?\Illuminate\Support\Carbon $updatedAt): ?string
    {
        if (! $url) {
            return null;
        }

        $version = $updatedAt?->timestamp ?? time();
        $sep     = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$sep}v={$version}";
    }
}
