<?php

namespace Modules\Bagisto\Mappers;

use Modules\Tenant\Models\Tenant;

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

        // Bagisto'nun `address.*` alanları `required_with:address` olduğu için
        // hepsi dolu değilse hiç göndermiyoruz — kısmi adres 422'ye yol açar.
        // Türkçe adres hiyerarşisi Bagisto'nun `state`/`city` alanlarına
        // İl/İlçe olarak eşlenir (bkz. Bagisto tr dil dosyası: state => "İl").
        if ($tenant->address && $tenant->country && $tenant->city && $tenant->district && $tenant->postal_code) {
            $payload['address'] = [
                'company_name' => $tenant->legal_name ?: $tenant->name,
                'vat_id' => $tenant->tax_number,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address' => $tenant->address,
                'country' => $tenant->country,
                'state' => $tenant->city,
                'city' => $tenant->district,
                'postcode' => $tenant->postal_code,
                'phone' => $tenant->phone,
                'email' => $owner->email,
            ];
        }

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
