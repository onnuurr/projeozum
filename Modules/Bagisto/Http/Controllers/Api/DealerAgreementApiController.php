<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Bagisto\Models\DealerAgreement;
use Modules\Bagisto\Models\DealerAgreementAcceptance;
use Modules\Bagisto\Services\BagistoOrderSyncService;
use Modules\Bagisto\Services\DealerAgreementService;
use Modules\Tenant\Models\Tenant;

/**
 * Bayi portalı (Bagisto) → sözleşmeler.
 *
 * index: güncel metinler (mağaza kısa süreli önbelleğe alır, bayinin gördüğü sürümü başvuruyla geri gönderir —
 * TenantApplicationApiController doğrular). `?locale=en`: İngilizce metinler (bekleme aşamasında).
 * `blocked_countries`: gönderim yapılmayan ülke kodları (mağaza ödeme ekranında uygular).
 *
 * pending / accept: portala giriş yapmış bayiden zorunlu yeniden onay (bayi e-postasıyla eşlenir — sipariş
 * webhook'uyla aynı kural, BagistoOrderSyncService::resolveTenantForEmail).
 */
class DealerAgreementApiController extends Controller
{
    public function __construct(
        private DealerAgreementService $agreements,
        private BagistoOrderSyncService $sync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['nullable', Rule::in(array_keys(DealerAgreement::LOCALES))]]);
        $locale = $data['locale'] ?? DealerAgreement::DEFAULT_LOCALE;

        return response()->json([
            'data' => $this->agreements->feed($locale),
            'locale' => $locale,
            // Gönderim yapılmayan ülkeler (ISO 3166-1 alpha-2): mağaza bu ülkelere teslimat adresi/sipariş kabul etmez.
            'blocked_countries' => $this->agreements->blockedCountries(),
        ]);
    }

    /** Bayiden onay bekleyen sözleşmeler (bayinin bilgileriyle doldurulmuş). Boş liste = portal serbest. */
    public function pending(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request->validate(['email' => ['required', 'email']])['email']);

        return response()->json(['data' => $tenant ? $this->agreements->pendingForTenant($tenant) : (object) []]);
    }

    /** Bekleyen sözleşmelerin onayı: her sürüm bekleyen ve güncel olmalı; doldurulmuş kopya arşive yazılır. */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'accepted' => ['required', 'array', 'min:1'],
            'accepted.*' => ['required', 'string', 'max:32'],
            'annexes' => ['nullable', 'array'],
            'annexes.*' => ['nullable', 'array'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        $tenant = $this->tenant($data['email']);
        abort_unless($tenant, 404, 'Bayi bulunamadı.');

        $pending = $this->agreements->pendingForTenant($tenant);

        foreach ($data['accepted'] as $key => $version) {
            if (($pending[$key]['version'] ?? null) !== $version) {
                throw ValidationException::withMessages(['accepted' => __('bagisto::messages.agreements.renewal_not_pending', ['key' => $key, 'version' => $version])]);
            }
        }

        foreach ($data['accepted'] as $key => $version) {
            $this->agreements->recordTenantAcceptance(
                $tenant, $key, $version, DealerAgreementAcceptance::CONTEXT_RENEWAL, $data['ip_address'] ?? null,
                annexVersions: array_filter((array) ($data['annexes'][$key] ?? []), 'is_string'),
            );
        }

        return response()->json(['data' => $this->agreements->pendingForTenant($tenant)]);
    }

    private function tenant(string $email): ?Tenant
    {
        return $this->sync->resolveTenantForEmail($email);
    }
}
