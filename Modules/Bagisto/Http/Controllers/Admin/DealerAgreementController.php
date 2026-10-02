<?php

namespace Modules\Bagisto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bagisto\Http\Requests\PublishDealerAgreementRequest;
use Modules\Bagisto\Models\DealerAgreement;
use Modules\Bagisto\Models\DealerAgreementAcceptance;
use Modules\Bagisto\Models\DealerAgreementDraft;
use Modules\Bagisto\Models\DealerAgreementVersion;
use Modules\Bagisto\Services\DealerAgreementService;

/**
 * İş Ortakları › Bayi Sözleşmeleri: bayi portalında onaylatılan sözleşme ve onay metinleri.
 * Metin zengin metin editöründe (HTML, sunucuda süzülür) yazılır, {{company.legal_name}} gibi etiketler içerebilir; önizleme sunucuda örnek
 * bayi verisiyle çizilir (Bagisto'ya giden HTML'in birebir aynısı). Kaydetmek = yeni sürüm yayımlamak.
 *
 * Onay arşivi (acceptances / printAcceptance): bayinin onay anındaki doldurulmuş kopyası. Başvuru
 * ekranından (Tenant modülü) çağrılır; başvuru yetkisine (tenant-application.approve) bağlıdır.
 */
class DealerAgreementController extends Controller
{
    public function __construct(private DealerAgreementService $agreements) {}

    public function index(): Response
    {
        return Inertia::render('Bagisto::DealerAgreements', [
            'tagGroups' => $this->agreements->tagGroups(),
            'seller' => $this->agreements->seller(),
            'sellerFields' => DealerAgreementService::SELLER_FIELDS,
            'customTags' => $this->agreements->customTags(),
            'countryOptions' => collect($this->agreements->countryOptions())
                ->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name, 'description' => $code])
                ->values(),
            'blockedCountries' => $this->agreements->blockedCountries(),
            'blockedRegions' => $this->agreements->blockedRegions(),
            'locales' => DealerAgreement::LOCALES,
            'liveLocales' => DealerAgreement::LIVE_LOCALES,
            'agreements' => $this->agreements->all()->map(fn (DealerAgreement $agreement) => [
                ...$this->presentForEditor($agreement),
                'annexes' => $agreement->annexes->map(fn (DealerAgreement $annex) => $this->presentForEditor($annex))->values(),
            ])->values(),
        ]);
    }

    /** Ana sözleşmeye ek (Ek-N) açar. */
    public function createAnnex(Request $request, DealerAgreement $agreement): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $annex = $this->agreements->createAnnex($agreement, $data['title']);

        return back()->with('success', __('bagisto::messages.agreements.annex_created', ['label' => $annex->annexLabel(), 'number' => $annex->annex_number]));
    }

    /** Eki kaldırır (pasif; geçmiş ve onay arşivi korunur). */
    public function retireAnnex(DealerAgreement $agreement): RedirectResponse
    {
        abort_unless($agreement->isAnnex(), 404);

        $this->agreements->retireAnnex($agreement);

        return back()->with('success', __('bagisto::messages.agreements.annex_retired', ['label' => $agreement->annexLabel()]));
    }

    /**
     * Düzenleyici için sözleşme / ek verisi: sürümler (editöre yüklenecek HTML ile), taslaklar, sıradaki sürüm.
     *
     * @return array<string, mixed>
     */
    private function presentForEditor(DealerAgreement $agreement): array
    {
        return [
            'id' => $agreement->id,
            'key' => $agreement->key,
            'title' => $agreement->title,
            'display' => $agreement->display,
            'description' => $agreement->description,
            'annexNumber' => $agreement->annex_number,
            'annexLabel' => $agreement->annexLabel(),
            'nextVersion' => collect(array_keys(DealerAgreement::LOCALES))
                ->mapWithKeys(fn (string $locale) => [$locale => $this->agreements->nextVersion($agreement, $locale)]),
            // Yayımlanmamış taslaklar: dil → {title, body, changeNote, updatedAt, editor}
            'drafts' => $agreement->drafts->mapWithKeys(fn (DealerAgreementDraft $draft) => [$draft->locale => [
                'title' => $draft->title ?? '',
                'body' => $this->agreements->editorHtml($draft->body, $draft->format),
                'changeNote' => $draft->change_note ?? '',
                'updatedAt' => $draft->updated_at?->toIso8601String(),
                'editor' => $draft->editor?->name,
            ]])->all() ?: (object) [],
            'versions' => $agreement->versions->map(fn (DealerAgreementVersion $version) => [
                'id' => $version->id,
                'locale' => $version->locale,
                'version' => $version->version,
                'title' => $version->title,
                // Editöre yüklenecek hâli (eski Markdown sürümler HTML'e çevrilir).
                'body' => $this->agreements->editorHtml($version->body, $version->format),
                'format' => $version->format,
                'requiresAcceptance' => $version->requires_acceptance,
                'html' => $this->agreements->render(
                    $version->body,
                    $agreement->display,
                    [...$this->agreements->sampleVariables($version->version, $version->locale), ...($version->variables_snapshot ?? [])],
                    $version->format,
                ),
                'changeNote' => $version->change_note,
                'publishedAt' => $version->published_at?->toIso8601String(),
                'publisher' => $version->publisher?->name,
            ])->values(),
        ];
    }

    /** Taslak önizleme: örnek bayi verisiyle doldurulur; tanımsız etiketler ayrıca bildirilir. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
            'display' => ['required', Rule::in([DealerAgreement::DISPLAY_DOCUMENT, DealerAgreement::DISPLAY_CONSENT])],
            'version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(array_keys(DealerAgreement::LOCALES))],
            'format' => ['nullable', Rule::in([DealerAgreementVersion::FORMAT_HTML, DealerAgreementVersion::FORMAT_MARKDOWN])],
            'agreement' => ['nullable', 'integer'],
        ]);

        // Ana sözleşme önizlemesinde {{annex.N}} atıfları ek başlıklarıyla bağlantıya dönüşür.
        $agreement = isset($data['agreement']) ? DealerAgreement::query()->with('annexes')->find($data['agreement']) : null;
        $previewLocale = $data['locale'] ?? DealerAgreement::DEFAULT_LOCALE;
        $annexLabels = $agreement && ! $agreement->isAnnex()
            ? $agreement->annexes->mapWithKeys(fn (DealerAgreement $annex) => [
                $annex->annex_number => $annex->annexLabel($previewLocale).': '.($this->agreements->current($annex, $previewLocale)?->title ?? $annex->title),
            ])->all()
            : [];

        $vars = $this->agreements->sampleVariables($data['version'] ?? null, $data['locale'] ?? DealerAgreement::DEFAULT_LOCALE);
        $format = $data['format'] ?? DealerAgreementVersion::FORMAT_HTML;
        $body = (string) ($data['body'] ?? '');

        return response()->json([
            'title' => $this->agreements->renderTitle((string) ($data['title'] ?? ''), $vars),
            'html' => $this->agreements->render($body, $data['display'], $vars, $format, $annexLabels),
            'unknown' => $this->agreements->unknownTags(($data['title'] ?? '').' '.$body, $agreement),
        ]);
    }

    public function publish(PublishDealerAgreementRequest $request, DealerAgreement $agreement): RedirectResponse
    {
        $data = $request->validated();

        $version = $this->agreements->publish(
            $agreement,
            $data['title'],
            $data['body'],
            $data['change_note'] ?? null,
            $request->user(),
            $data['locale'] ?? DealerAgreement::DEFAULT_LOCALE,
            requiresAcceptance: (bool) ($data['requires_acceptance'] ?? false),
        );

        return back()->with('success', __($version->requires_acceptance ? 'bagisto::messages.agreements.published_reaccept' : 'bagisto::messages.agreements.published', [
            'title' => $version->title,
            'version' => $version->version,
        ]));
    }

    /** Taslak olarak kaydet (yayımlanmaz, bayi portalına gitmez). */
    public function saveDraft(Request $request, DealerAgreement $agreement): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(DealerAgreement::LOCALES))],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->agreements->saveDraft($agreement, $data['locale'], $data['title'] ?? null, $data['body'] ?? null, $data['change_note'] ?? null, $request->user());

        return back()->with('success', __('bagisto::messages.agreements.draft_saved'));
    }

    /** Taslağı sil: düzenleyici yayımlanmış güncel sürüme döner. */
    public function discardDraft(Request $request, DealerAgreement $agreement): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(DealerAgreement::LOCALES))]]);

        $this->agreements->discardDraft($agreement, $data['locale']);

        return back()->with('success', __('bagisto::messages.agreements.draft_discarded'));
    }

    /** Firma bilgilerimiz ({{seller.*}}) ve özel etiketler ({{custom.*}}). */
    public function updateSettings(Request $request): RedirectResponse
    {
        // Tamamen boş bırakılmış özel etiket satırları (Etiket ekle → doldurulmadı) yok sayılır; formun geri kalanını reddettirmesin.
        $request->merge([
            'custom_tags' => collect((array) $request->input('custom_tags', []))
                ->filter(fn ($tag) => is_array($tag) && collect($tag)->only(['key', 'description', 'value'])->filter(fn ($v) => filled($v))->isNotEmpty())
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'seller' => ['nullable', 'array'],
            ...collect(DealerAgreementService::SELLER_FIELDS)
                ->mapWithKeys(fn ($label, string $field) => ['seller.'.$field => ['nullable', 'string', 'max:500']])
                ->all(),
            'custom_tags' => ['nullable', 'array', 'max:50'],
            'custom_tags.*.key' => ['required', 'string', 'regex:'.DealerAgreementService::CUSTOM_KEY_PATTERN],
            'custom_tags.*.description' => ['nullable', 'string', 'max:120'],
            'custom_tags.*.value' => ['nullable', 'string', 'max:1000'],
            'blocked_countries' => ['nullable', 'array', 'max:250'],
            'blocked_countries.*' => ['string', Rule::in(array_keys($this->agreements->countryOptions()))],
            'blocked_regions' => ['nullable', 'array'],
            'blocked_regions.*' => ['nullable', 'string', 'max:1000'],
        ], [
            'custom_tags.*.key.regex' => 'Anahtar yalnızca küçük harf, rakam ve alt çizgi içerebilir (en fazla 40 karakter).',
            'custom_tags.*.key.required' => 'Etiket anahtarı zorunludur.',
        ], [
            ...collect(DealerAgreementService::SELLER_FIELDS)->mapWithKeys(fn (string $label, string $field) => ['seller.'.$field => $label])->all(),
            'custom_tags.*.key' => 'etiket anahtarı',
            'custom_tags.*.description' => 'etiket açıklaması',
            'custom_tags.*.value' => 'etiket değeri',
            'blocked_countries.*' => 'ülke',
            'blocked_regions.*' => 'bölgeler',
        ]);

        $custom = $data['custom_tags'] ?? [];
        $duplicate = collect($custom)->pluck('key')->duplicates()->first();
        if ($duplicate !== null) {
            throw ValidationException::withMessages(['custom_tags' => __('bagisto::messages.agreements.custom_tag_duplicate', ['key' => $duplicate])]);
        }

        $this->agreements->updateSettings($data['seller'] ?? [], $custom);
        $addendum = $this->agreements->updateShippingRestrictions($data['blocked_countries'] ?? [], $data['blocked_regions'] ?? [], $request->user());

        return back()->with('success', $addendum
            ? __('bagisto::messages.agreements.settings_saved_addendum', ['version' => $addendum->version])
            : __('bagisto::messages.agreements.settings_saved'));
    }

    /** Başvurunun onaylanmış sözleşme kopyaları (başvuru detayında listelenir). */
    public function acceptances(Request $request): JsonResponse
    {
        $data = $request->validate(['application' => ['required', 'integer']]);

        return response()->json([
            'data' => DealerAgreementAcceptance::query()
                ->where('tenant_application_id', $data['application'])
                ->orderBy('accepted_at')
                ->orderBy('id')
                ->get()
                ->map(fn (DealerAgreementAcceptance $acceptance) => [
                    'id' => $acceptance->id,
                    'title' => $acceptance->title,
                    'version' => $acceptance->version,
                    'locale' => $acceptance->locale,
                    'context' => DealerAgreementAcceptance::CONTEXT_LABELS[$acceptance->context] ?? $acceptance->context,
                    'acceptedAt' => $acceptance->accepted_at?->toIso8601String(),
                    'printUrl' => route('dealer-agreements.acceptances.print', $acceptance),
                ]),
        ]);
    }

    /** Yazdırılabilir onay kopyası (tarayıcıdan PDF olarak kaydedilebilir). */
    public function printAcceptance(DealerAgreementAcceptance $acceptance): View
    {
        return view('bagisto::agreements.acceptance-print', [
            'acceptance' => $acceptance,
            'contextLabel' => DealerAgreementAcceptance::CONTEXT_LABELS[$acceptance->context] ?? $acceptance->context,
        ]);
    }
}
