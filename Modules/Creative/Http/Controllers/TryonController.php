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
use Modules\Creative\Http\Controllers\Concerns\HandlesCreativeReview;
use Modules\Creative\Http\Requests\GenerateTryonRequest;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\GarmentLabel;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Enhancement\GarmentDetailClassifierContract;
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

    public function index(): Response
    {
        $productsTotal = Product::query()->count();

        $products = $this->mapProducts(
            $this->searchProducts(null)->take(self::PRODUCT_PAGE_SIZE)->get(['id', 'name']),
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

        // Ürün seçilmeden önce (ör. yalnız 'creative.approve' yetkisiyle onay bekleyen
        // her ürünü inceleyen bir onaylayıcı için) genel/global son giydirmeler akışı —
        // ürüne özel liste {@see results()} uç noktasından axios ile ayrıca çekilir.
        $results = $this->mapTryonResults(
            $this->tryonResultsQuery()->latest()->limit(60)->get(),
        );

        return Inertia::render('Creative::CreativeTryon', [
            'products'         => $products,
            'productsTotal'    => $productsTotal,
            'mannequins'       => $mannequins,
            'poses'            => $poses,
            'results'          => $results,
            'rejectionReasons' => $this->rejectionReasonGroups('tryon'),
        ]);
    }

    /**
     * Ürün seçici arama uç noktası (axios ile çağrılır, Inertia sayfası değil) —
     * {@see index()}'in yalnız ilk {@see PRODUCT_PAGE_SIZE} ürünü göndermesinin
     * karşılığı: kullanıcı arama kutusuna yazınca kalan katalog buradan gelir.
     */
    public function products(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim()->value() ?: null;

        $products = $this->mapProducts(
            $this->searchProducts($q)->take(self::PRODUCT_PAGE_SIZE)->get(['id', 'name']),
        );

        return response()->json(['data' => $products]);
    }

    /**
     * Belirli bir ürünün TÜM giydirme geçmişi (axios ile çağrılır) — index()'teki
     * global son-60 listesi ürüne özel değildir; bir ürün seçildiğinde galeri bu
     * uç noktadan gelen ürüne-özel listeye geçer, böylece başka ürünlerin
     * giydirmeleri karışmaz.
     */
    public function results(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        $results = $this->mapTryonResults(
            $this->tryonResultsQuery()
                ->where('product_id', $data['product_id'])
                ->latest()
                ->limit(200)
                ->get(),
        );

        return response()->json(['data' => $results]);
    }

    private function tryonResultsQuery(): Builder
    {
        return TryonResult::query()->with([
            'product:id,name', 'mannequin:id,name', 'pose:id,label',
            'productImage:id,path,is_cover', 'creator:id,name', 'reviewer:id,name',
            'reviewChats' => fn ($q) => $q->orderBy('created_at')->with('user:id,name'),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,TryonResult>  $results
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function mapTryonResults(\Illuminate\Support\Collection $results)
    {
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
            // Onaydan önce henüz product_images satırı yok; staged (bekleyen) önizleme gösterilir.
            'image_url'       => $r->productImage?->url ?? Media::url($r->staged_image_path),
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
        ]);
    }

    private function searchProducts(?string $q): Builder
    {
        return Product::query()
            ->when($q, fn ($query) => $query->where('name', 'ilike', "%{$q}%"))
            ->with(['images' => fn ($iq) => $iq->reorder()->orderByDesc('is_cover')->orderBy('sort_order')])
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
        return $products->map(function (Product $p) {
            $garment = $p->images->first();

            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'cover'       => $garment?->url,
                'has_garment' => $garment !== null,
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
                    'garment_image' => 'Bu ürünün giydirilecek bir fotoğrafı yok. Önce ürüne fotoğraf ekleyin ya da burada bir görsel yükleyin.',
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

        $queued = $service->queue($product, $mannequin, $request->validated('pose_ids'), auth()->id(), $garmentImagePath, $garmentExtras);

        if ($queued->isEmpty()) {
            return back()->with('error', 'Seçili pozların hiçbiri hazır değil.');
        }

        foreach ($queued as $result) {
            GenerateOnModelJob::dispatch($result->id, $result->generation_token);
        }

        return back()->with('success', $queued->count() . ' görsel giydirme kuyruğuna alındı.');
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
                'tryon_driver'           => $result->tryon_driver,
                'tryon_model'            => $result->tryon_model,
                'generation_duration_ms' => $result->generation_duration_ms,
                'image_url'              => $result->productImage?->url ?? Media::url($result->staged_image_path),
                'garment_image_url'      => $this->url($result->garment_image_path),
                'is_cover'               => (bool) $result->productImage?->is_cover,
                'created_at'             => $result->created_at?->toDateTimeString(),
                'creator_name'           => $result->creator?->name,
                'review_status'          => $result->review_status,
                'review_note'            => $result->review_note,
                'reviewer_name'          => $result->reviewer?->name,
                'reviewed_at'            => $result->reviewed_at?->toDateTimeString(),
            ],
            'garmentExtras' => $garmentExtras,
            'garmentScan'   => $scanPayload,
        ]);
    }

    public function approve(TryonResult $result, ProductOnModelService $service, ReviewNotifier $notifier): RedirectResponse
    {
        if ($result->review_status !== TryonResult::REVIEW_PENDING) {
            return back()->with('error', 'Bu sonuç onay bekliyor durumda değil.');
        }

        $service->publish($result);

        $result->update([
            'review_status' => TryonResult::REVIEW_APPROVED,
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($result, true);

        return back()->with('success', 'Giydirme görseli onaylandı ve ürüne eklendi.');
    }

    public function reject(TryonResult $result, Request $request, ReviewNotifier $notifier): RedirectResponse
    {
        if ($result->review_status !== TryonResult::REVIEW_PENDING) {
            return back()->with('error', 'Bu sonuç onay bekliyor durumda değil.');
        }

        $review = $this->validatedReview($request);

        $result->update([
            'review_status' => TryonResult::REVIEW_REJECTED,
            'review_note'   => $review['note'],
            'review_tags'   => $review['tags'],
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($result, false);

        return back()->with('success', 'Giydirme görseli reddedildi.');
    }

    /**
     * Giydirme çıktısını (üretilen ürün görselini) ürünün kapağı yapar.
     */
    public function setCover(TryonResult $result): RedirectResponse
    {
        $result->loadMissing('productImage');
        $image = $result->productImage;

        if ($result->status !== TryonResult::STATUS_DONE || ! $image) {
            return back()->with('error', 'Bu sonuç henüz kapak yapılamaz.');
        }

        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->update(['is_cover' => false]);

            $image->update(['is_cover' => true]);
        });

        return back()->with('success', 'Ürün kapağı güncellendi.');
    }

    /**
     * Giydirme sonucunu, ürettiği ürün görselini (onaylandıysa) ve staged
     * (onaylanmamış) dosyayı siler.
     */
    public function destroyResult(TryonResult $result, ProductOnModelService $service): RedirectResponse
    {
        $service->destroy($result);

        return back()->with('success', 'Giydirme sonucu silindi.');
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
}
