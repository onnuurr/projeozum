<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;

class TenantMarketplaceController extends Controller
{
    public function index(Request $request, Tenant $tenant): Response
    {
        $this->authorizeTenantScope($request, $tenant);

        $credentials = $tenant->marketplaceCredentials()
            ->orderBy('marketplace')
            ->get()
            ->map(fn (TenantMarketplaceCredential $c) => [
                'id'                => $c->id,
                'marketplace'       => $c->marketplace,
                'marketplace_label' => $c->marketplace_label,
                'supplier_id'       => $c->supplier_id,
                'store_name'        => $c->store_name,
                'is_active'         => $c->is_active,
                'last_sync_at'      => optional($c->last_sync_at)->format('Y-m-d H:i'),
                'last_error'        => $c->last_error,
                'notes'             => $c->notes,
                'has_api_key'       => ! empty($c->getAttributes()['api_key']),
                'has_api_secret'    => ! empty($c->getAttributes()['api_secret']),
                'updated_at'        => optional($c->updated_at)->format('Y-m-d H:i'),
            ]);

        $marketplaces = collect(TenantMarketplaceCredential::MARKETPLACES)
            ->map(fn ($code) => [
                'code'  => $code,
                'label' => TenantMarketplaceCredential::LABELS[$code],
            ])
            ->values();

        return Inertia::render('Tenant::TenantMarketplace', [
            'tenant' => [
                'id'   => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'type' => $tenant->type ? [
                    'id'   => $tenant->type->id,
                    'code' => $tenant->type->code,
                    'name' => $tenant->type->name,
                ] : null,
            ],
            'credentials'  => $credentials,
            'marketplaces' => $marketplaces,
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenantScope($request, $tenant);

        $data = $this->validateCredential($request, $tenant->id);

        TenantMarketplaceCredential::create([
            'tenant_id'    => $tenant->id,
            'marketplace'  => $data['marketplace'],
            'supplier_id'  => $data['supplier_id'] ?? null,
            'store_name'   => $data['store_name'] ?? null,
            'api_key'      => $data['api_key'] ?? null,
            'api_secret'   => $data['api_secret'] ?? null,
            'is_active'    => $data['is_active'] ?? true,
            'notes'        => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Pazaryeri bağlantısı eklendi.');
    }

    public function update(Request $request, Tenant $tenant, TenantMarketplaceCredential $credential): RedirectResponse
    {
        $this->authorizeTenantScope($request, $tenant);
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

        return back()->with('success', 'Pazaryeri bağlantısı güncellendi.');
    }

    public function toggle(Request $request, Tenant $tenant, TenantMarketplaceCredential $credential): RedirectResponse
    {
        $this->authorizeTenantScope($request, $tenant);
        $this->ensureBelongsToTenant($credential, $tenant);

        $credential->update(['is_active' => ! $credential->is_active]);

        return back()->with(
            'success',
            $credential->is_active ? 'Bağlantı aktif edildi.' : 'Bağlantı pasifleştirildi.',
        );
    }

    public function destroy(Request $request, Tenant $tenant, TenantMarketplaceCredential $credential): RedirectResponse
    {
        $this->authorizeTenantScope($request, $tenant);
        $this->ensureBelongsToTenant($credential, $tenant);

        $credential->delete();

        return back()->with('success', 'Pazaryeri bağlantısı silindi.');
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
            'marketplace.unique' => 'Bu tenant için bu pazaryeri zaten ekli.',
            'marketplace.in'     => 'Geçersiz pazaryeri seçimi.',
        ]);
    }

    private function authorizeTenantScope(Request $request, Tenant $tenant): void
    {
        $user = $request->user();
        if ($user->isSuperadmin()) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $tenant->id) {
            abort(403, 'Bu tenant\'ın pazaryeri ayarlarına erişiminiz yok.');
        }
    }

    private function ensureBelongsToTenant(TenantMarketplaceCredential $credential, Tenant $tenant): void
    {
        abort_if((int) $credential->tenant_id !== (int) $tenant->id, 404);
    }
}
