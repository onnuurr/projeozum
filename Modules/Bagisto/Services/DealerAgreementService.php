<?php

namespace Modules\Bagisto\Services;

use App\Contracts\PlatformSettings;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Validation\ValidationException;
use ResourceBundle;
use Collator;
use Locale;
use Modules\Bagisto\Models\DealerAgreement;
use Modules\Bagisto\Models\DealerAgreementAcceptance;
use Modules\Bagisto\Models\DealerAgreementDraft;
use Modules\Bagisto\Models\DealerAgreementVersion;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayi Sözleşmeleri: bayi portalında onaylatılan metinlerin sürümlü yönetimi.
 *
 * Metinler zengin metin editöründe yazılır ve HTML saklanır (format = html; HTMLPurifier ile yalnızca
 * izinli biçimler kalır — bkz. sanitize()). 2026-10-02 öncesi sürümler Markdown'dır (format = markdown) ve
 * öyle çizilir. Metinler {{company.legal_name}} gibi etiketler içerebilir (bkz. tags()):
 * company.* / user.* / dealer.* bayinin bilgileri, seller.* bizim firma bilgilerimiz, custom.* yönetim
 * ekranında tanımlanan serbest etiketler, shipping.blocked_countries gönderim yapılmayan ülkeler (hepsi platform
 * ayarlarında, 'dealer_agreement' grubu). Gönderim yapılmayan ülkeler Bagisto'da teslimat adresi/sipariş engeli olarak
 * da uygulanır (API: blocked_countries).
 * Etiketler Markdown'dan önce, kaçışlanmış değerlerle doldurulur; ardından HTML'e çevrilir
 * (ham HTML atılır, güvensiz bağlantılara izin verilmez). Değeri bilinmeyen etiket boş çizgi olur
 * (ön başvuru formu, ödeme ekranı gibi henüz bayi bilgisinin olmadığı yerler).
 *
 * Her yayın yeni sürüm numarası alır ("<yıl>/v<n>", yıl ve dil içinde artar); yayımlanmış sürüm değiştirilmez.
 * Diller (DealerAgreement::LOCALES) ayrı yayımlanır; bayi portalına şimdilik yalnız Türkçe gider (LIVE_LOCALES),
 * İngilizce hazırlanır ve API'den `?locale=en` ile okunabilir — mağaza açtığında kullanılır.
 * Bayi onay verdiğinde doldurulmuş metin onay arşivine yazılır (recordAcceptance).
 *
 * Ekler (Ek-1, Ek-2 …): ana sözleşmeye bağlı alt sözleşmelerdir (DealerAgreement::parent_id), kendi sürüm
 * serileri vardır. Ana metinde {{annex.N}} "Ek-N: Başlık" bağlantısına dönüşür; ekler ana metnin ardından
 * gösterilir ve onay kopyasına bayinin gördüğü sürümleriyle birlikte yazılır.
 *
 * Yeniden onay: "requires_acceptance" ile yayımlanan sürüm (ana sözleşme ya da eki), o tarihten önce bayi olmuş ve
 * henüz onaylamamış bayilere portala girişte zorunlu adım olarak sunulur (pendingForTenant / recordTenantAcceptance).
 * Gönderim yapılmayan ülkeler değişince "Gönderim Kısıtlaması Ek Sözleşmesi"nin (shipping_restrictions) yeni sürümü
 * otomatik yayımlanır; liste o sürüme dondurulur (variables_snapshot).
 */
class DealerAgreementService
{
    public const TAG_PATTERN = '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/';

    /** Değeri olmayan etiketin yerine basılan boşluk (Markdown'da kaçışlı alt çizgi). */
    private const BLANK = '\_\_\_\_\_\_\_\_\_\_\_\_';

    public const SETTINGS_GROUP = 'dealer_agreement';

    /** Bizim firma bilgilerimiz: alan → etiket açıklaması ({{seller.<alan>}}). */
    public const SELLER_FIELDS = [
        'legal_name' => 'Ticari unvanımız',
        'name' => 'Marka adımız',
        'tax_number' => 'VKN\'miz',
        'tax_office' => 'Vergi dairemiz',
        'mersis' => 'MERSİS numaramız',
        'trade_registry' => 'Ticaret sicil numaramız',
        'address' => 'Merkez adresimiz',
        'phone' => 'Telefonumuz',
        'email' => 'E-posta adresimiz',
        'kep' => 'KEP adresimiz',
        'website' => 'Web sitemiz',
    ];

    public const SHIPPING_ADDENDUM = 'shipping_restrictions';

    public const CUSTOM_KEY_PATTERN = '/^[a-z0-9_]{1,40}$/';

    /** Editörün üretebildiği ve süzgeçten geçen HTML (kalın, italik, altı çizili, başlık, liste, hizalama, bağlantı). */
    private const ALLOWED_HTML = 'p[style],br,strong,b,em,i,u,s,h2[style],h3[style],h4[style],ul,ol[start],li,blockquote,hr,a[href|target|rel],'
        .'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]';

    private const ANNEX_TAG = '/\{\{\s*annex\.(\d+)\s*\}\}/';

    private ?HTMLPurifier $purifier = null;

    /** intl bölge listesinde ülke olmayan / tekrar eden kodlar. */
    private const NON_COUNTRY_CODES = ['EU', 'EZ', 'UN', 'ZZ', 'QO', 'XA', 'XB', 'AC', 'CP', 'CQ', 'DG', 'EA', 'IC', 'TA'];

    private const COMPANY_TYPES = ['sahis' => 'Şahıs şirketi', 'limited' => 'Limited şirketi', 'anonim' => 'Anonim şirketi'];

    private const DEALER_MODELS = ['dropshipping' => 'Dropshipping', 'toptan' => 'Toptan serili alım', 'hibrit' => 'Hibrit'];

    /** İngilizce metinlerde seçenek etiketleri. */
    private const COMPANY_TYPES_EN = ['sahis' => 'Sole proprietorship', 'limited' => 'Limited company', 'anonim' => 'Joint-stock company'];

    private const DEALER_MODELS_EN = ['dropshipping' => 'Dropshipping', 'toptan' => 'Wholesale (size runs)', 'hibrit' => 'Hybrid'];

    public function __construct(private PlatformSettings $settings) {}

    /**
     * Sözleşmelerde kullanılabilen etiketler (Bayi E-postaları'ndaki user.* / company.* adlarıyla uyumlu).
     *
     * @return array<string, string>
     */
    public function tags(): array
    {
        return [
            ...$this->dealerTags(),
            ...collect(self::SELLER_FIELDS)->mapWithKeys(fn (string $label, string $field) => ['seller.'.$field => $label])->all(),
            'shipping.blocked_countries' => 'Gönderim yapılmayan ülkeler ve bölgeler (sözleşme dilinde)',
            'shipping.updated_at' => 'Gönderim listesinin son değişiklik tarihi',
            ...collect($this->customTags())->mapWithKeys(fn (array $tag) => ['custom.'.$tag['key'] => $tag['description'] ?: $tag['key']])->all(),
        ];
    }

    /**
     * Etiket grupları (yönetim ekranındaki çipler için).
     *
     * @return array<int, array{label: string, tags: array<int, array{tag: string, description: string}>}>
     */
    public function tagGroups(): array
    {
        $list = fn (array $tags) => collect($tags)->map(fn (string $description, string $tag) => ['tag' => $tag, 'description' => $description])->values()->all();
        $all = $this->tags();

        return array_values(array_filter([
            ['label' => 'Bayi', 'tags' => $list($this->dealerTags())],
            ['label' => 'Firmamız', 'tags' => $list(array_filter($all, fn ($tag) => str_starts_with($tag, 'seller.'), ARRAY_FILTER_USE_KEY))],
            ['label' => 'Gönderim', 'tags' => $list(array_filter($all, fn ($tag) => str_starts_with($tag, 'shipping.'), ARRAY_FILTER_USE_KEY))],
            ['label' => 'Özel', 'tags' => $list(array_filter($all, fn ($tag) => str_starts_with($tag, 'custom.'), ARRAY_FILTER_USE_KEY))],
        ], fn (array $group) => $group['tags'] !== []));
    }

    /** @return array<string, string> */
    private function dealerTags(): array
    {
        return [
            'company.legal_name' => 'Ticari unvan (yoksa firma adı)',
            'company.name' => 'Firma / mağaza adı',
            'company.tax_number' => 'VKN / TCKN',
            'company.tax_office' => 'Vergi dairesi',
            'company.address' => 'Firma adresi (ilçe / il ile)',
            'user.name' => 'Yetkili adı soyadı',
            'user.email' => 'Yetkili e-posta adresi',
            'user.phone' => 'Yetkili telefonu',
            'application.reference' => 'Başvuru referans kodu',
            'company.type' => 'Şirket türü',
            'dealer.model' => 'Bayilik modeli (dropshipping / toptan / hibrit)',
            'dealer.code' => 'İş ortağı kodu (hesap açılınca)',
            'agreement.date' => 'Onay tarihi',
            'agreement.version' => 'Sözleşme sürümü',
        ];
    }

    /**
     * Firma bilgilerimiz (kayıtlı ham değerler).
     *
     * @return array<string, ?string>
     */
    public function seller(): array
    {
        $stored = $this->settings->group(self::SETTINGS_GROUP)['seller'] ?? [];

        return collect(self::SELLER_FIELDS)
            ->mapWithKeys(fn (string $label, string $field) => [$field => is_array($stored) && filled($stored[$field] ?? null) ? (string) $stored[$field] : null])
            ->all();
    }

    /**
     * Özel etiketler (yönetim ekranında tanımlanır).
     *
     * @return array<int, array{key: string, description: string, value: string}>
     */
    public function customTags(): array
    {
        $stored = $this->settings->group(self::SETTINGS_GROUP)['custom_tags'] ?? [];

        return collect(is_array($stored) ? $stored : [])
            ->filter(fn ($tag) => is_array($tag) && preg_match(self::CUSTOM_KEY_PATTERN, (string) ($tag['key'] ?? '')))
            ->map(fn (array $tag) => [
                'key' => (string) $tag['key'],
                'description' => (string) ($tag['description'] ?? ''),
                'value' => (string) ($tag['value'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Firma bilgilerini ve özel etiketleri kaydeder. Güncel bir sözleşme sürümünde kullanılan özel etiket
     * silinemez (metinde boş çizgiye dönerdi).
     *
     * @param  array<string, ?string>  $seller
     * @param  array<int, array{key: string, description?: ?string, value?: ?string}>  $customTags
     */
    public function updateSettings(array $seller, array $customTags): void
    {
        $keys = collect($customTags)->pluck('key')->all();
        $removed = collect($this->customTags())->pluck('key')->diff($keys);

        if ($removed->isNotEmpty()) {
            $currents = DealerAgreement::query()->get()->flatMap(fn (DealerAgreement $agreement) => collect(array_keys(DealerAgreement::LOCALES))
                ->map(fn (string $locale) => $this->current($agreement, $locale))
                ->filter());
            $used = $removed->filter(fn (string $key) => $currents
                ->contains(fn (DealerAgreementVersion $version) => preg_match('/\{\{\s*custom\.'.preg_quote($key, '/').'\s*\}\}/', $version->title.' '.$version->body)));

            if ($used->isNotEmpty()) {
                throw ValidationException::withMessages(['custom_tags' => __('bagisto::messages.agreements.custom_tag_in_use', [
                    'tags' => $used->map(fn ($key) => '{{custom.'.$key.'}}')->implode(', '),
                ])]);
            }
        }

        $this->settings->putGroup(self::SETTINGS_GROUP, [
            'seller' => collect(self::SELLER_FIELDS)->mapWithKeys(fn ($label, string $field) => [$field => filled($seller[$field] ?? null) ? trim((string) $seller[$field]) : null])->all(),
            'custom_tags' => collect($customTags)->map(fn (array $tag) => [
                'key' => $tag['key'],
                'description' => trim((string) ($tag['description'] ?? '')),
                'value' => trim((string) ($tag['value'] ?? '')),
            ])->values()->all(),
        ]);
    }

    /**
     * Seçilebilir ülkeler: ISO 3166-1 alpha-2 kod → o dildeki ad (ada göre sıralı).
     *
     * @return array<string, string>
     */
    public function countryOptions(string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        $bundle = ResourceBundle::create($locale, 'ICUDATA-region')?->get('Countries');
        $countries = [];

        foreach ($bundle ?? [] as $code => $name) {
            if (preg_match('/^[A-Z]{2}$/', (string) $code) && ! in_array($code, self::NON_COUNTRY_CODES, true)) {
                $countries[$code] = (string) $name;
            }
        }

        $collator = new Collator($locale);
        uasort($countries, fn (string $a, string $b) => $collator->compare($a, $b));

        return $countries;
    }

    /** @return array<int, string> Gönderim yapılmayan ülke kodları (sipariş engeli + sözleşme etiketi). */
    public function blockedCountries(): array
    {
        $stored = $this->settings->group(self::SETTINGS_GROUP)['blocked_countries'] ?? [];

        return array_values(array_filter((array) $stored, fn ($code) => is_string($code) && preg_match('/^[A-Z]{2}$/', $code)));
    }

    /**
     * Ülke kodu olmayan bölgeler (ör. Kırım), dil başına serbest metin; yalnız sözleşmede yazılır, sipariş engeline girmez.
     *
     * @return array<string, string>
     */
    public function blockedRegions(): array
    {
        $stored = $this->settings->group(self::SETTINGS_GROUP)['blocked_regions'] ?? [];

        return collect(array_keys(DealerAgreement::LOCALES))
            ->mapWithKeys(fn (string $locale) => [$locale => is_array($stored) ? trim((string) ($stored[$locale] ?? '')) : ''])
            ->all();
    }

    /** {{shipping.blocked_countries}}: ülkelerin o dildeki adları + bölgeler metni. */
    public function blockedCountriesText(string $locale = DealerAgreement::DEFAULT_LOCALE): string
    {
        $names = $this->blockedCountries()
            ? collect($this->blockedCountries())->map(fn (string $code) => Locale::getDisplayRegion('-'.$code, $locale) ?: $code)->sort(fn ($a, $b) => (new Collator($locale))->compare($a, $b))->values()->all()
            : [];

        return collect([...$names, $this->blockedRegions()[$locale] ?? ''])->filter()->implode(', ');
    }

    /**
     * @param  array<int, string>  $countries
     * @param  array<string, ?string>  $regions
     */
    /**
     * Listeyi kaydeder; ülkeler ya da bölgeler değiştiyse ek sözleşmenin yeni sürümünü yayımlar (yeniden onay).
     *
     * @return DealerAgreementVersion|null yayımlanan ek sözleşme sürümü (Türkçe), değişiklik yoksa null
     */
    public function updateShippingRestrictions(array $countries, array $regions, ?User $actor = null): ?DealerAgreementVersion
    {
        $valid = array_keys($this->countryOptions());
        $newCountries = array_values(array_unique(array_intersect(array_map('strtoupper', $countries), $valid)));
        $newRegions = collect(array_keys(DealerAgreement::LOCALES))
            ->mapWithKeys(fn (string $locale) => [$locale => trim((string) ($regions[$locale] ?? ''))])
            ->all();

        $oldCountries = $this->blockedCountries();
        $changed = array_diff($newCountries, $oldCountries) !== [] || array_diff($oldCountries, $newCountries) !== []
            || $newRegions !== $this->blockedRegions();

        if (! $changed) {
            return null;
        }

        $this->settings->putGroup(self::SETTINGS_GROUP, [
            'blocked_countries' => $newCountries,
            'blocked_regions' => $newRegions,
            'blocked_updated_at' => now()->timezone(config('app.timezone'))->format('d.m.Y'),
        ]);

        return $this->publishShippingAddendum($actor);
    }

    /**
     * Ek sözleşmenin her dildeki güncel metnini (şablon) yeni sürüm olarak yayımlar: güncel liste sürüme dondurulur,
     * mevcut bayilerden girişte onay istenir. Şablonu olmayan dil atlanır.
     */
    public function publishShippingAddendum(?User $actor = null): ?DealerAgreementVersion
    {
        $addendum = DealerAgreement::query()->where('key', self::SHIPPING_ADDENDUM)->where('is_active', true)->first();

        if (! $addendum) {
            return null;
        }

        $published = null;

        foreach (array_keys(DealerAgreement::LOCALES) as $locale) {
            $template = $this->current($addendum, $locale, fresh: true);

            if (! $template) {
                continue;
            }

            $list = $this->blockedCountriesText($locale);
            $version = DB::transaction(function () use ($addendum, $template, $locale, $list, $actor) {
                DealerAgreement::query()->whereKey($addendum->id)->lockForUpdate()->first();

                return $addendum->versions()->create([
                    'locale' => $locale,
                    'version' => $this->nextVersion($addendum, $locale),
                    'title' => $template->title,
                    'body' => $template->body,
                    'format' => $template->format,
                    'requires_acceptance' => true,
                    'variables_snapshot' => [
                        'shipping.blocked_countries' => $list,
                        'shipping.updated_at' => $this->settings->group(self::SETTINGS_GROUP)['blocked_updated_at'] ?? now()->format('d.m.Y'),
                    ],
                    'change_note' => 'Gönderim yapılmayan ülkeler güncellendi: '.($list !== '' ? $list : '—'),
                    'published_by' => $actor?->id,
                    'published_at' => now(),
                ]);
            });

            if ($locale === DealerAgreement::DEFAULT_LOCALE) {
                $published = $version;
            }
        }

        return $published;
    }

    /**
     * Bayiden bağımsız değerler: firma bilgilerimiz + özel etiketler.
     *
     * @return array<string, string>
     */
    public function staticVariables(string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        return array_filter([
            ...collect($this->seller())->mapWithKeys(fn ($value, string $field) => ['seller.'.$field => $value])->all(),
            'shipping.blocked_countries' => $this->blockedCountriesText($locale),
            'shipping.updated_at' => $this->settings->group(self::SETTINGS_GROUP)['blocked_updated_at'] ?? null,
            ...collect($this->customTags())->mapWithKeys(fn (array $tag) => ['custom.'.$tag['key'] => $tag['value']])->all(),
        ], fn ($value) => filled($value));
    }

    /**
     * Metindeki tanımsız etiketler.
     *
     * @return array<int, string>
     */
    public function unknownTags(?string $text, ?DealerAgreement $agreement = null): array
    {
        preg_match_all(self::TAG_PATTERN, (string) $text, $matches);

        // {{annex.N}}: yalnızca ana sözleşmede, yürürlükteki ek numaralarıyla.
        $annexTags = $agreement && ! $agreement->isAnnex()
            ? $agreement->annexes()->pluck('annex_number')->map(fn ($n) => 'annex.'.$n)->all()
            : [];

        return array_values(array_unique(array_diff($matches[1], array_keys($this->tags()), $annexTags)));
    }

    /** Ana sözleşmeye yeni ek (sıradaki numara; kaldırılmış eklerin numarası yeniden kullanılmaz). */
    public function createAnnex(DealerAgreement $parent, string $title): DealerAgreement
    {
        if ($parent->isAnnex() || $parent->display !== DealerAgreement::DISPLAY_DOCUMENT) {
            throw ValidationException::withMessages(['title' => __('bagisto::messages.agreements.annex_not_allowed')]);
        }

        return DB::transaction(function () use ($parent, $title) {
            DealerAgreement::query()->whereKey($parent->id)->lockForUpdate()->first();

            $number = (int) DealerAgreement::query()->where('parent_id', $parent->id)->max('annex_number') + 1;

            return DealerAgreement::create([
                'parent_id' => $parent->id,
                'annex_number' => $number,
                'key' => $parent->key.'-ek'.$number,
                'title' => trim($title),
                'display' => DealerAgreement::DISPLAY_DOCUMENT,
                'sort_order' => $number,
                'is_active' => true,
            ]);
        });
    }

    /** Eki kaldırır (pasif). Ana metnin güncel bir sürümünde {{annex.N}} ile anılıyorsa kaldırılamaz. */
    public function retireAnnex(DealerAgreement $annex): void
    {
        $parent = $annex->parent;
        $pattern = '/\{\{\s*annex\.'.$annex->annex_number.'\s*\}\}/';

        $referenced = collect(array_keys(DealerAgreement::LOCALES))
            ->map(fn (string $locale) => $this->current($parent, $locale))
            ->filter()
            ->contains(fn (DealerAgreementVersion $version) => preg_match($pattern, $version->body));

        if ($referenced) {
            throw ValidationException::withMessages(['annex' => __('bagisto::messages.agreements.annex_in_use', ['label' => $annex->annexLabel()])]);
        }

        $annex->update(['is_active' => false]);
    }

    /**
     * Başvurudan etiket değerleri. Hesap açıldıysa (iş ortağı oluştu) güncel firma bilgisi oradan okunur.
     *
     * @return array<string, string>
     */
    public function variables(TenantApplication $application, ?Carbon $acceptedAt = null, ?string $version = null, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        $english = $locale === 'en';
        $tenant = $application->convertedTenant;
        $pick = fn (string $field) => trim((string) ($tenant?->{$field} ?: $application->{$field}));

        $name = $pick('name');
        $address = collect([$pick('address'), collect([$pick('district'), $pick('city')])->filter()->implode(' / ')])
            ->filter()->implode(', ');

        $details = $application->details ?? [];

        return array_filter([
            ...$this->staticVariables($locale),
            'company.legal_name' => $pick('legal_name') ?: $name,
            'company.name' => $name,
            'company.tax_number' => $pick('tax_number'),
            'company.tax_office' => $pick('tax_office'),
            'company.address' => $address,
            'user.name' => $pick('contact_person'),
            'user.email' => trim((string) $application->contact_email),
            'user.phone' => $pick('contact_phone'),
            'application.reference' => $application->reference,
            'company.type' => ($english ? self::COMPANY_TYPES_EN : self::COMPANY_TYPES)[$details['company_type'] ?? ''] ?? null,
            'dealer.model' => ($english ? self::DEALER_MODELS_EN : self::DEALER_MODELS)[$details['dealer_model'] ?? ''] ?? null,
            'dealer.code' => $tenant?->code,
            'agreement.date' => $acceptedAt?->copy()->timezone(config('app.timezone'))->format('d.m.Y'),
            'agreement.version' => $version,
        ], fn ($value) => filled($value));
    }

    /**
     * Yönetim ekranı önizlemesi için örnek değerler.
     *
     * @return array<string, string>
     */
    public function sampleVariables(?string $version = null, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        $english = $locale === 'en';

        return [
            ...$this->staticVariables($locale),
            'company.legal_name' => 'Lila Bebek ve Çocuk Giyim Ltd. Şti.',
            'company.name' => 'Lila Butik',
            'company.tax_number' => '1234567899',
            'company.tax_office' => 'Kadıköy',
            'company.address' => 'Caddebostan Mah. Bağdat Cad. No:142, Kadıköy / İstanbul',
            'user.name' => 'Buse Karaca',
            'user.email' => 'buse@lilabutik.com',
            'user.phone' => '+90 532 840 21 00',
            'application.reference' => 'OZ-BAYI-2026-0007',
            'company.type' => $english ? 'Limited company' : 'Limited şirketi',
            'dealer.model' => 'Dropshipping',
            'dealer.code' => 'LILA01',
            'agreement.date' => now()->timezone(config('app.timezone'))->format('d.m.Y'),
            'agreement.version' => $version ?? now()->year.'/v1',
        ];
    }

    /**
     * Yönetim ekranı: tüm sözleşmeler, sürüm geçmişiyle (yeniden eskiye).
     *
     * @return Collection<int, DealerAgreement>
     */
    public function all(): Collection
    {
        return DealerAgreement::query()
            ->whereNull('parent_id')
            ->with([
                'versions.publisher:id,name', 'drafts.editor:id,name',
                'annexes.versions.publisher:id,name', 'annexes.drafts.editor:id,name',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Yayın: başlık ve metin o dildeki güncel sürümle aynıysa ya da tanımsız etiket varsa yeni sürüm açılmaz. */
    public function publish(DealerAgreement $agreement, string $title, string $body, ?string $changeNote, User $publisher, string $locale = DealerAgreement::DEFAULT_LOCALE, string $format = DealerAgreementVersion::FORMAT_HTML, bool $requiresAcceptance = false): DealerAgreementVersion
    {
        $title = trim($title);
        $body = $format === DealerAgreementVersion::FORMAT_HTML ? $this->sanitize($body) : $this->normalize($body);

        if (blank(trim(strip_tags($body)))) {
            throw ValidationException::withMessages(['body' => __('bagisto::messages.agreements.body_required')]);
        }

        if ($unknown = $this->unknownTags($title.' '.$body, $agreement)) {
            throw ValidationException::withMessages(['body' => __('bagisto::messages.agreements.unknown_tags', [
                'tags' => collect($unknown)->map(fn ($tag) => '{{'.$tag.'}}')->implode(', '),
            ])]);
        }

        return DB::transaction(function () use ($agreement, $title, $body, $changeNote, $publisher, $locale, $format, $requiresAcceptance) {
            // Aynı anda iki yayın aynı sürüm numarasını almasın.
            DealerAgreement::query()->whereKey($agreement->id)->lockForUpdate()->first();

            $current = $this->current($agreement, $locale, fresh: true);

            if ($current && $current->title === $title && $current->format === $format && $this->normalize($current->body) === $body) {
                throw ValidationException::withMessages(['body' => __('bagisto::messages.agreements.unchanged')]);
            }

            // Yayımlanan dilin taslağı artık güncel sürümdür.
            $agreement->drafts()->where('locale', $locale)->delete();

            return $agreement->versions()->create([
                'locale' => $locale,
                'version' => $this->nextVersion($agreement, $locale),
                'title' => $title,
                'body' => $body,
                'format' => $format,
                'requires_acceptance' => $requiresAcceptance,
                'change_note' => filled($changeNote) ? trim($changeNote) : null,
                'published_by' => $publisher->id,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * Taslağı kaydeder (sözleşme + dil başına tek taslak, üzerine yazılır). Yayımlanmaz, sürüm almaz;
     * tanımsız etiket taslakta serbesttir (yayında reddedilir).
     */
    public function saveDraft(DealerAgreement $agreement, string $locale, ?string $title, ?string $body, ?string $changeNote, User $editor): DealerAgreementDraft
    {
        return DealerAgreementDraft::updateOrCreate(
            ['dealer_agreement_id' => $agreement->id, 'locale' => $locale],
            [
                'title' => filled($title) ? trim($title) : null,
                'body' => filled($body) ? $this->sanitize($body) : null,
                'format' => DealerAgreementVersion::FORMAT_HTML,
                'change_note' => filled($changeNote) ? trim($changeNote) : null,
                'updated_by' => $editor->id,
            ],
        );
    }

    public function discardDraft(DealerAgreement $agreement, string $locale): void
    {
        $agreement->drafts()->where('locale', $locale)->delete();
    }

    /** O dilde bu yıl içindeki en büyük sürümün bir fazlası: 2026/v1, 2026/v2 … */
    public function nextVersion(DealerAgreement $agreement, string $locale = DealerAgreement::DEFAULT_LOCALE): string
    {
        $year = now()->year;

        $max = DealerAgreementVersion::query()
            ->where('dealer_agreement_id', $agreement->id)
            ->where('locale', $locale)
            ->where('version', 'like', $year.'/v%')
            ->pluck('version')
            ->map(fn (string $version) => (int) Str::after($version, $year.'/v'))
            ->max() ?? 0;

        return $year.'/v'.($max + 1);
    }

    /**
     * Etiketleri doldurup HTML'e çevirir. $vars null ise tüm etiketler boş çizgi olur.
     * HTML biçiminde metin yeniden süzülür, değerler HTML kaçışıyla yazılır; consent (onay kutusu) metninde
     * paragraf sarmalayıcıları kaldırılır (tek satır).
     *
     * @param  array<string, string>|null  $vars
     */
    public function render(string $body, string $display = DealerAgreement::DISPLAY_DOCUMENT, ?array $vars = null, string $format = DealerAgreementVersion::FORMAT_MARKDOWN, array $annexLabels = []): string
    {
        if ($format === DealerAgreementVersion::FORMAT_HTML) {
            // {{annex.N}} → "Ek-N: Başlık" bağlantısı (ekler aynı belgede #ek-N çapasıyla gösterilir).
            $html = preg_replace_callback(
                self::ANNEX_TAG,
                fn (array $m) => isset($annexLabels[(int) $m[1]])
                    ? '<a href="#ek-'.(int) $m[1].'">'.e($annexLabels[(int) $m[1]]).'</a>'
                    : e('Ek-'.(int) $m[1]),
                $this->sanitize($body),
            );

            $html = preg_replace_callback(
                self::TAG_PATTERN,
                fn (array $m) => filled($vars[$m[1]] ?? null) ? e((string) $vars[$m[1]]) : '____________',
                $html,
            );

            return $display === DealerAgreement::DISPLAY_CONSENT ? $this->inline($html) : $html;
        }

        $markdown = preg_replace_callback(
            self::TAG_PATTERN,
            fn (array $m) => filled($vars[$m[1]] ?? null) ? $this->escapeMarkdown((string) $vars[$m[1]]) : self::BLANK,
            $this->normalize($body),
        );

        $options = ['html_input' => 'strip', 'allow_unsafe_links' => false];

        return trim($display === DealerAgreement::DISPLAY_CONSENT
            ? Str::inlineMarkdown($markdown, $options)
            : Str::markdown($markdown, $options));
    }

    /**
     * Editöre yüklenecek HTML: HTML sürüm olduğu gibi, Markdown sürüm HTML'e çevrilmiş hâliyle
     * (etiketler {{…}} olarak kalır).
     */
    public function editorHtml(?string $body, ?string $format): string
    {
        if (blank($body)) {
            return '';
        }

        return $format === DealerAgreementVersion::FORMAT_HTML
            ? $this->sanitize($body)
            : trim(Str::markdown($this->normalize($body), ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /** Editörden gelen HTML'i izinli biçimlere indirger (script, stil, olay öznitelikleri vb. atılır). */
    public function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        if ($this->purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', self::ALLOWED_HTML);
            $config->set('CSS.AllowedProperties', ['text-align']);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('AutoFormat.RemoveEmpty', false);
            $this->purifier = new HTMLPurifier($config);
        }

        return trim($this->purifier->purify(str_replace("\r\n", "\n", $html)));
    }

    /** Onay kutusu metni: blok etiketler kaldırılır, paragraflar boşlukla birleşir. */
    private function inline(string $html): string
    {
        $html = preg_replace('#</(p|h[2-4]|li|blockquote)>\s*<(p|h[2-4]|li|blockquote)[^>]*>#i', ' ', $html);

        return trim(strip_tags($html, '<strong><b><em><i><u><s><a><br>'));
    }

    /** Başlıktaki etiketler düz metin olarak doldurulur. */
    public function renderTitle(string $title, ?array $vars = null): string
    {
        return preg_replace_callback(
            self::TAG_PATTERN,
            fn (array $m) => filled($vars[$m[1]] ?? null) ? (string) $vars[$m[1]] : '…',
            $title,
        );
    }

    /**
     * Bagisto'ya giden genel veri: anahtar → güncel sürüm (firma bilgilerimiz ve özel etiketler dolu,
     * bayi etiketleri boş çizgi). Hiç yayımlanmamış
     * sözleşme `version: null` döner (mağaza onu bağlantısız gösterir).
     *
     * @return array<string, array{title: string, display: string, version: ?string, html: ?string, published_at: ?string}>
     */
    public function feed(string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        return $this->currentAgreements()
            ->mapWithKeys(fn (DealerAgreement $agreement) => [$agreement->key => $this->present($agreement, $this->staticVariables($locale), $locale)])
            ->all();
    }

    /**
     * Başvuruya özel, bayinin bilgileriyle doldurulmuş güncel metinler (durum sayfası, üyelik tamamlama).
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{title: string, display: string, version: ?string, html: ?string, published_at: ?string}>
     */
    public function forApplication(TenantApplication $application, array $keys, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        return $this->currentAgreements($keys)
            ->mapWithKeys(fn (DealerAgreement $agreement) => [$agreement->key => $this->present(
                $agreement,
                $this->variables($application, now(), $this->current($agreement, $locale)?->version, $locale),
                $locale,
            )])
            ->all();
    }

    /** Bayinin onayladığını bildirdiği sürüm gerçekten yayımlanmış mı (geçmiş sürümler dahil)? */
    public function isPublishedVersion(string $key, ?string $version, string $locale = DealerAgreement::DEFAULT_LOCALE): bool
    {
        return $this->findVersion($key, $version, $locale) !== null;
    }

    /** Ek anahtarı ana sözleşmenin yürürlükteki bir eki mi ve sürüm o ekte yayımlanmış mı? */
    public function isPublishedAnnexVersion(string $parentKey, string $annexKey, ?string $version, string $locale = DealerAgreement::DEFAULT_LOCALE): bool
    {
        $isAnnex = DealerAgreement::query()
            ->where('key', $annexKey)
            ->where('is_active', true)
            ->whereHas('parent', fn ($query) => $query->where('key', $parentKey))
            ->exists();

        return $isAnnex && $this->isPublishedVersion($annexKey, $version, $locale);
    }

    /** Sözleşmenin o dildeki güncel sürümü (Türkçe için yüklü ilişki kullanılır). */
    public function current(DealerAgreement $agreement, string $locale = DealerAgreement::DEFAULT_LOCALE, bool $fresh = false): ?DealerAgreementVersion
    {
        if ($locale === DealerAgreement::DEFAULT_LOCALE && ! $fresh && $agreement->relationLoaded('currentVersion')) {
            return $agreement->currentVersion;
        }

        return DealerAgreementVersion::query()
            ->where('dealer_agreement_id', $agreement->id)
            ->where('locale', $locale)
            ->latest('published_at')
            ->latest('id')
            ->first();
    }

    /**
     * Onay anında doldurulmuş metni arşive yazar. Sürüm bulunamazsa (eski mağaza önbelleği, sürümsüz onay)
     * kayıt açılmaz — sürüm doğrulaması zaten API'de yapılır.
     */
    public function recordAcceptance(TenantApplication $application, string $key, ?string $version, string $context, ?string $ip = null, string $locale = DealerAgreement::DEFAULT_LOCALE, array $annexVersions = []): ?DealerAgreementAcceptance
    {
        $row = $this->findVersion($key, $version, $locale);

        if (! $row) {
            return null;
        }

        $acceptedAt = now();

        return $this->storeAcceptance($row, $this->variables($application, $acceptedAt, $row->version, $row->locale), $acceptedAt, $context, $ip, $annexVersions, $application->id, $application->converted_tenant_id);
    }

    /**
     * Portalda girişte yeniden onay: iş ortağının (bayi) bilgileriyle doldurulmuş kopya arşive yazılır.
     *
     * @param  array<string, string>  $annexVersions
     */
    public function recordTenantAcceptance(Tenant $tenant, string $key, ?string $version, string $context, ?string $ip = null, string $locale = DealerAgreement::DEFAULT_LOCALE, array $annexVersions = []): ?DealerAgreementAcceptance
    {
        $row = $this->findVersion($key, $version, $locale);

        if (! $row) {
            return null;
        }

        $acceptedAt = now();
        $application = $this->applicationFor($tenant);

        return $this->storeAcceptance($row, $this->variablesForTenant($tenant, $acceptedAt, $row->version, $row->locale), $acceptedAt, $context, $ip, $annexVersions, $application?->id, $tenant->id);
    }

    /**
     * Bayiden onay bekleyen sözleşmeler (portala girişte zorunlu adım): ana sözleşmenin ya da eklerinin
     * "requires_acceptance" ile yayımlanmış en son sürümünün tarihinden sonra bu sözleşmeyi onaylamamışsa bekler.
     * Ek sözleşme (gönderim kısıtlaması), o tarihten sonra çerçeve sözleşmeyi onaylamış bayiden istenmez (yeni
     * çerçeve onayı güncel listeyi zaten içerir). Bayinin bilgileriyle doldurulmuş hâliyle döner.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pendingForTenant(Tenant $tenant, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        $acceptedAt = fn (array $keys) => DealerAgreementAcceptance::query()
            ->whereIn('agreement_key', $keys)
            ->where(fn ($q) => $q->where('tenant_id', $tenant->id)
                ->orWhereIn('tenant_application_id', TenantApplication::query()->where('converted_tenant_id', $tenant->id)->select('id')))
            ->max('accepted_at');

        return $this->currentAgreements()
            ->filter(function (DealerAgreement $agreement) use ($locale, $acceptedAt) {
                $required = DealerAgreementVersion::query()
                    ->whereIn('dealer_agreement_id', [$agreement->id, ...$agreement->annexes->pluck('id')])
                    ->where('locale', $locale)
                    ->where('requires_acceptance', true)
                    ->max('published_at');

                if (! $required || ! $this->current($agreement, $locale)) {
                    return false;
                }

                $keys = $agreement->key === self::SHIPPING_ADDENDUM ? [$agreement->key, 'framework'] : [$agreement->key];
                $last = $acceptedAt($keys);

                return ! $last || Carbon::parse($last)->lt(Carbon::parse($required));
            })
            ->mapWithKeys(fn (DealerAgreement $agreement) => [$agreement->key => $this->present(
                $agreement,
                $this->variablesForTenant($tenant, now(), $this->current($agreement, $locale)?->version, $locale),
                $locale,
            )])
            ->all();
    }

    /**
     * İş ortağından etiket değerleri: portal başvurusu varsa başvurudan (hesap açılınca güncel firma bilgisi iş ortağından
     * okunur), yoksa (SaaS'ta elle açılmış bayi) doğrudan iş ortağı kaydından.
     *
     * @return array<string, string>
     */
    public function variablesForTenant(Tenant $tenant, ?Carbon $acceptedAt = null, ?string $version = null, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        if ($application = $this->applicationFor($tenant)) {
            return $this->variables($application, $acceptedAt, $version, $locale);
        }

        $field = fn (string $name) => trim((string) $tenant->{$name});
        $address = collect([$field('address'), collect([$field('district'), $field('city')])->filter()->implode(' / ')])->filter()->implode(', ');

        return array_filter([
            ...$this->staticVariables($locale),
            'company.legal_name' => $field('legal_name') ?: $field('name'),
            'company.name' => $field('name'),
            'company.tax_number' => $field('tax_number'),
            'company.tax_office' => $field('tax_office'),
            'company.address' => $address,
            'user.name' => $field('contact_person'),
            'user.email' => trim((string) ($tenant->primaryUser?->email ?? $tenant->email)),
            'user.phone' => $field('contact_phone') ?: $field('phone'),
            'dealer.code' => $field('code'),
            'agreement.date' => $acceptedAt?->copy()->timezone(config('app.timezone'))->format('d.m.Y'),
            'agreement.version' => $version,
        ], fn ($value) => filled($value));
    }

    private function applicationFor(Tenant $tenant): ?TenantApplication
    {
        return TenantApplication::query()->where('converted_tenant_id', $tenant->id)->latest('id')->first();
    }

    /** Sürüme dondurulmuş değerler (ör. ek sözleşmedeki ülke listesi) canlı değerlerin önüne geçer. */
    private function withSnapshot(?array $vars, DealerAgreementVersion $row): ?array
    {
        return $row->variables_snapshot ? [...($vars ?? []), ...$row->variables_snapshot] : $vars;
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<string, string>  $annexVersions
     */
    private function storeAcceptance(DealerAgreementVersion $row, array $vars, Carbon $acceptedAt, string $context, ?string $ip, array $annexVersions, ?int $applicationId, ?int $tenantId): DealerAgreementAcceptance
    {
        $vars = $this->withSnapshot($vars, $row);
        $display = $row->agreement->display;

        // Ekler: bayinin gördüğü sürümler (mağaza bildirmediyse güncel sürüm); ana metnin ardına, her biri ayrı bölüm.
        $annexes = $this->presentAnnexes($row->agreement->loadMissing('annexes'), $vars, $row->locale, $annexVersions);
        $labels = collect($annexes)->mapWithKeys(fn (array $annex) => [$annex['number'] => $annex['label'].': '.$annex['title']])->all();
        $html = $this->render($row->body, $display, $vars, $row->format, $labels)
            .collect($annexes)->map(fn (array $annex) => '<section class="agreement-annex" id="ek-'.$annex['number'].'">'
                .'<h2>'.e(mb_strtoupper($annex['label'], 'UTF-8').' — '.$annex['title']).'</h2>'
                .$annex['html'].'</section>')->implode('');

        return DealerAgreementAcceptance::create([
            'dealer_agreement_version_id' => $row->id,
            'tenant_application_id' => $applicationId,
            'tenant_id' => $tenantId,
            'agreement_key' => $row->agreement->key,
            'version' => $row->version,
            'locale' => $row->locale,
            'context' => $context,
            'title' => $this->renderTitle($row->title, $vars),
            'html' => $html,
            'data' => [
                'variables' => $vars,
                'display' => $display,
                'annexes' => collect($annexes)->map(fn (array $annex) => collect($annex)->only(['key', 'number', 'title', 'version'])->all())->all(),
            ],
            'ip_address' => $ip,
            'accepted_at' => $acceptedAt,
        ]);
    }

    /**
     * @param  array<int, string>|null  $keys
     * @return Collection<int, DealerAgreement>
     */
    private function currentAgreements(?array $keys = null): Collection
    {
        return DealerAgreement::query()
            ->whereNull('parent_id')
            ->with(['currentVersion', 'annexes.currentVersion'])
            ->when($keys !== null, fn ($query) => $query->whereIn('key', $keys))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  array<string, string>|null  $vars
     * @return array{title: string, display: string, version: ?string, html: ?string, published_at: ?string}
     */
    private function present(DealerAgreement $agreement, ?array $vars = null, string $locale = DealerAgreement::DEFAULT_LOCALE): array
    {
        $current = $this->current($agreement, $locale);
        $annexes = $this->presentAnnexes($agreement, $vars, $locale);
        $labels = collect($annexes)->mapWithKeys(fn (array $annex) => [$annex['number'] => $annex['label'].': '.$annex['title']])->all();

        return [
            'title' => $current ? $this->renderTitle($current->title, $vars) : $agreement->title,
            'display' => $agreement->display,
            'version' => $current?->version,
            'html' => $current ? $this->render($current->body, $agreement->display, $this->withSnapshot($vars, $current), $current->format, $labels) : null,
            'published_at' => $current?->published_at?->toIso8601String(),
            // Yayımlanmış ekler: mağaza ana metnin ardından gösterir, gösterdiği sürümleri onayla geri gönderir.
            'annexes' => $annexes,
        ];
    }

    /**
     * Ana sözleşmenin o dilde yayımlanmış ekleri. $versions verilirse (onay anı) o sürümler kullanılır.
     *
     * @param  array<string, string>  $versions  ek anahtarı → sürüm
     * @return array<int, array{key: string, number: int, label: string, title: string, version: string, html: string, published_at: ?string}>
     */
    private function presentAnnexes(DealerAgreement $agreement, ?array $vars, string $locale, array $versions = []): array
    {
        if ($agreement->isAnnex()) {
            return [];
        }

        return $agreement->annexes
            ->map(function (DealerAgreement $annex) use ($vars, $locale, $versions) {
                $row = isset($versions[$annex->key])
                    ? ($this->findVersion($annex->key, $versions[$annex->key], $locale) ?? $this->current($annex, $locale))
                    : $this->current($annex, $locale);

                return $row ? [
                    'key' => $annex->key,
                    'number' => $annex->annex_number,
                    'label' => $annex->annexLabel($locale),
                    'title' => $this->renderTitle($row->title, $vars),
                    'version' => $row->version,
                    'html' => $this->render($row->body, DealerAgreement::DISPLAY_DOCUMENT, $this->withSnapshot($vars, $row), $row->format),
                    'published_at' => $row->published_at?->toIso8601String(),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function findVersion(string $key, ?string $version, string $locale = DealerAgreement::DEFAULT_LOCALE): ?DealerAgreementVersion
    {
        if (blank($version)) {
            return null;
        }

        return DealerAgreementVersion::query()
            ->with('agreement')
            ->where('locale', $locale)
            ->where('version', $version)
            ->whereHas('agreement', fn ($query) => $query->where('key', $key))
            ->first();
    }

    /** Değer Markdown olarak yorumlanmasın (ör. unvandaki "*", "_" ya da "<"): ASCII noktalama kaçışlanır. */
    private function escapeMarkdown(string $value): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', str_replace(["\r", "\n"], ' ', $value));
    }

    private function normalize(string $body): string
    {
        return trim(str_replace("\r\n", "\n", $body));
    }
}
