<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;

class MarketplaceHubController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $credentials = TenantMarketplaceCredential::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $providers = collect(TenantMarketplaceCredential::MARKETPLACES)
            ->map(function (string $code) use ($credentials) {
                $cred = $credentials->firstWhere('marketplace', $code);

                return [
                    'code'          => $code,
                    'label'         => TenantMarketplaceCredential::LABELS[$code] ?? $code,
                    'connected'     => $cred !== null,
                    'active'        => $cred?->is_active ?? false,
                    'store_name'    => $cred?->store_name,
                    'last_sync_at'  => optional($cred?->last_sync_at)->toIso8601String(),
                    // Trendyol live; diğerleri stub-only şu an.
                    'live_ready'    => $code === 'trendyol',
                    'credential'    => $cred ? [
                        'id'                 => $cred->id,
                        'supplier_id'        => $cred->supplier_id,
                        'store_name'         => $cred->store_name,
                        'is_active'          => $cred->is_active,
                        'notes'              => $cred->notes,
                        'last_error'         => $cred->last_error,
                        'api_key_preview'    => $this->maskSecret($cred->api_key),
                        'api_secret_preview' => $this->maskSecret($cred->api_secret),
                        'updated_at'         => optional($cred->updated_at)->format('Y-m-d H:i'),
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Tenant::Portal/Marketplace/Index', [
            'tenant'    => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'providers' => $providers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $data = $this->validateCredential($request, $tenant->id);

        TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => $data['marketplace'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'store_name'  => $data['store_name'] ?? null,
            'api_key'     => $data['api_key'] ?? null,
            'api_secret'  => $data['api_secret'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'notes'       => $data['notes'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, TenantMarketplaceCredential $credential): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $this->ensureBelongsToTenant($credential, $tenant);

        $data = $this->validateCredential($request, $tenant->id, $credential->id);

        // Boş bırakılan secret alanları mevcut değeri korur — UI "değiştirmek için doldur" pattern'i.
        $payload = [
            'marketplace' => $data['marketplace'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'store_name'  => $data['store_name'] ?? null,
            'is_active'   => $data['is_active'] ?? $credential->is_active,
            'notes'       => $data['notes'] ?? null,
        ];

        if (! empty($data['api_key'])) {
            $payload['api_key'] = $data['api_key'];
        }
        if (! empty($data['api_secret'])) {
            $payload['api_secret'] = $data['api_secret'];
        }

        $credential->update($payload);

        return back();
    }

    public function toggle(Request $request, TenantMarketplaceCredential $credential): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $this->ensureBelongsToTenant($credential, $tenant);

        $credential->update(['is_active' => ! $credential->is_active]);

        return back();
    }

    public function destroy(Request $request, TenantMarketplaceCredential $credential): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $this->ensureBelongsToTenant($credential, $tenant);

        $credential->delete();

        return back();
    }

    private function validateCredential(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'marketplace' => [
                'required',
                Rule::in(TenantMarketplaceCredential::MARKETPLACES),
                Rule::unique('tenant_marketplace_credentials', 'marketplace')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'supplier_id' => ['nullable', 'string', 'max:64'],
            'store_name'  => ['nullable', 'string', 'max:191'],
            'api_key'     => ['nullable', 'string', 'max:500'],
            'api_secret'  => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ], [
            'marketplace.unique' => 'Bu pazaryeri için zaten bir bağlantınız var.',
            'marketplace.in'     => 'Geçersiz pazaryeri seçimi.',
        ]);
    }

    private function ensureBelongsToTenant(TenantMarketplaceCredential $credential, Tenant $tenant): void
    {
        abort_if((int) $credential->tenant_id !== (int) $tenant->id, 404);
    }

    /**
     * Son 4 karakteri görünür bırakır, gerisini maskeler (KVKK: tam değer bir daha ekranda gösterilmez).
     */
    private function maskSecret(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4).mb_substr($value, -4);
    }
}
