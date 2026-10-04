<?php

namespace Modules\Bagisto\Mappers;

use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAddress;

/**
 * `Tenant` + owner kullanıcısını, Bagisto tarafındaki
 * `Webkul\SaasSync\Http\Controllers\TenantWebhookController::handle()`
 * validasyonunun beklediği payload şekline çevirir.
 *
 * Bagisto müşteri hesabının e-postası owner'ın (portal giriş) e-postasıdır —
 * `Tenant.email` (iş/fatura iletişim alanı, nullable) DEĞİL. Bagisto sipariş
 * webhook'u da (OrderWebhookController) tenant'ı bu aynı owner e-postasıyla
 * eşleştirir.
 */
class TenantPayloadMapper
{
    /**
     * @return array<string, mixed>|null  Owner yoksa null (Bagisto'da açılacak
     *                                    bir hesap yok, event atlanır).
     */
    public function toActivatedPayload(Tenant $tenant, ?string $plainPassword): ?array
    {
        $owner = $tenant->primaryUser;

        if (! $owner) {
            return null;
        }

        [$firstName, $lastName] = $this->splitName($owner->name);

        $payload = [
            'event' => 'activated',
            'email' => $owner->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $tenant->phone,
            'password' => $plainPassword,
            'credit_limit' => (float) $tenant->credit_limit,
            'current_balance' => (float) $tenant->current_balance,
            // Bagisto bayiyi bu orana ait müşteri grubuna alır; mağazadaki tüm fiyatlar
            // SaaS satış fiyatı − bu oran olarak görünür (TenantAccessService::priceFor ile aynı).
            'discount_rate' => (float) $tenant->discount_rate,
        ];

        // Bagisto `activated` event'inde bunu zorunlu kılıyor — boşsa hiç
        // göndermiyoruz, aksi halde Bagisto 422 döner (bkz. TENANT_WEBHOOK_
        // REQUIREMENTS.md, Bagisto tarafı). SaaS admin panelinde bu alan
        // doldurulmadan tenant aktif edilirse senkron başarısız olur.
        if ($tenant->shipping_agreement_type) {
            $payload['shipping_agreement_type'] = $tenant->shipping_agreement_type;
            $payload['shipping_terms_accepted'] = $tenant->shipping_agreement_type === 'platform';
            // Bagisto'daki carrier_name bir takip numarası DEĞİL, bayinin
            // kullandığı kargo firmasının adıdır (merkezi carriers listesinden).
            $payload['carrier_name'] = $tenant->shipping_agreement_type === 'own'
                ? $tenant->carrier?->name
                : null;
        }

        // Fatura / sevk / iade adresleri Bagisto'da bayi hesabının salt-okunur adresleri olur
        // (`additional.saas_type` ile eşleşir). Liste her zaman gider: SaaS'ta silinen adres
        // Bagisto'dan da kalkar. Türkçe adres hiyerarşisi Bagisto'nun `state`/`city` alanlarına
        // İl/İlçe olarak eşlenir (bkz. Bagisto tr dil dosyası: state => "İl").
        $payload['addresses'] = $tenant->addresses
            ->filter(fn (TenantAddress $a) => $a->address && $a->city && $a->district)
            ->sortBy(fn (TenantAddress $a) => array_search($a->type, TenantAddress::TYPES, true))
            ->map(function (TenantAddress $a) use ($tenant, $owner, $firstName, $lastName) {
                [$contactFirst, $contactLast] = $a->contact_name ? $this->splitName($a->contact_name) : [$firstName, $lastName];

                return [
                    'type' => $a->type,
                    'label' => TenantAddress::LABELS[$a->type],
                    'company_name' => $tenant->legal_name ?: $tenant->name,
                    'vat_id' => $a->type === TenantAddress::TYPE_BILLING ? $tenant->tax_number : null,
                    'first_name' => $contactFirst,
                    'last_name' => $contactLast,
                    'address' => $a->address,
                    'country' => $a->country ?: 'TR',
                    'state' => $a->city,
                    'city' => $a->district,
                    'postcode' => $a->postal_code,
                    'phone' => $a->phone ?: $tenant->phone,
                    'email' => $owner->email,
                ];
            })
            ->values()
            ->all();

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toDeactivatedPayload(Tenant $tenant): ?array
    {
        $owner = $tenant->primaryUser;

        if (! $owner) {
            return null;
        }

        return [
            'event' => 'deactivated',
            'email' => $owner->email,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? $firstName;

        return [$firstName, $lastName];
    }
}
