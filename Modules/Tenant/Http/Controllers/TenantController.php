<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Carrier;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantType;
use Modules\Tenant\Services\TenantService;

class TenantController extends Controller
{
    public function __construct(private TenantService $service) {}

    public function index(Request $request): Response
    {
        $query = Tenant::query()
            ->with(['type:id,code,name', 'owner:id,tenant_id,name,email', 'carrier:id,code,name'])
            ->withCount('users');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($typeId = $request->query('type_id')) !== null && $typeId !== '') {
            $query->where('tenant_type_id', (int) $typeId);
        }

        if (($active = $request->query('active')) !== null && $active !== '') {
            $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOL));
        }

        $tenants = $query
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $t) => [
                'id'                => $t->id,
                'code'              => $t->code,
                'name'              => $t->name,
                'legal_name'        => $t->legal_name,
                'type'              => $t->type ? [
                    'id'   => $t->type->id,
                    'code' => $t->type->code,
                    'name' => $t->type->name,
                ] : null,
                'owner'             => $t->owner ? [
                    'id'    => $t->owner->id,
                    'name'  => $t->owner->name,
                    'email' => $t->owner->email,
                ] : null,
                'tax_number'        => $t->tax_number,
                'tax_office'        => $t->tax_office,
                'email'             => $t->email,
                'phone'             => $t->phone,
                'contact_person'    => $t->contact_person,
                'contact_phone'     => $t->contact_phone,
                'address'           => $t->address,
                'city'              => $t->city,
                'district'          => $t->district,
                'country'           => $t->country,
                'postal_code'       => $t->postal_code,
                'credit_limit'      => (float) $t->credit_limit,
                'current_balance'   => (float) $t->current_balance,
                'available_credit'  => $t->available_credit,
                'shipping_agreement_type'    => $t->shipping_agreement_type,
                'shipping_terms_accepted_at' => optional($t->shipping_terms_accepted_at)->format('Y-m-d H:i'),
                'carrier'                    => $t->carrier ? [
                    'id'   => $t->carrier->id,
                    'code' => $t->carrier->code,
                    'name' => $t->carrier->name,
                ] : null,
                'payment_term_days' => $t->payment_term_days,
                'discount_rate'     => (float) $t->discount_rate,
                'is_active'         => $t->is_active,
                'notes'             => $t->notes,
                'userCount'         => $t->users_count,
                'updatedAt'         => optional($t->updated_at)->format('Y-m-d'),
            ]);

        $types = TenantType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $carriers = Carrier::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Tenant::Tenants', [
            'tenants'  => $tenants,
            'types'    => $types,
            'carriers' => $carriers,
            'filters' => [
                'q'       => $search ?? '',
                'type_id' => $typeId ?? '',
                'active'  => $active ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->applyShippingAgreementTimestamp($this->validateTenant($request), null);

        $this->service->create($data);

        // Flash basılmıyor — Tenants.vue onSuccess'te kendi toast'unu gösteriyor.
        return redirect()->route('tenants.index');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->applyShippingAgreementTimestamp($this->validateTenant($request, $tenant), $tenant);

        $this->service->update($tenant, $data);

        return redirect()->route('tenants.index');
    }

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        if ($tenant->is_active) {
            $this->service->suspend($tenant);
        } else {
            $this->service->activate($tenant);
        }

        return back();
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if ($tenant->users()->exists()) {
            return back()->withErrors([
                'tenant' => 'Bu tenant\'a bağlı kullanıcılar var, önce kullanıcıları başka tenant\'a taşıyın.',
            ]);
        }

        $tenant->delete();

        return redirect()->route('tenants.index');
    }

    private function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
        // Owner hesabı oluştururken zorunlu; düzenlemede 'sometimes' — alanlar dolu
        // gelirse (bkz. Tenants.vue) mevcut owner kullanıcısı güncellenir
        // (bkz. TenantService::update, TenantUserService::updateOwner).
        $isCreate = $tenant === null;
        $ownerId  = $tenant?->owner?->id;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('tenants', 'code')->ignore($tenant?->id),
            ],
            'name'              => ['required', 'string', 'max:191'],
            'owner_name'        => [$isCreate ? 'required' : 'sometimes', 'string', 'max:191'],
            'owner_email'       => [$isCreate ? 'required' : 'sometimes', 'email', 'max:191', Rule::unique('users', 'email')->ignore($ownerId)],
            'owner_password'    => [$isCreate ? 'required' : 'sometimes', 'string', 'min:8', 'confirmed'],
            'legal_name'        => ['nullable', 'string', 'max:255'],
            'tenant_type_id'    => ['nullable', 'integer', 'exists:tenant_types,id'],
            'tax_number'        => ['nullable', 'string', 'max:32'],
            'tax_office'        => ['nullable', 'string', 'max:191'],
            'email'             => ['nullable', 'email', 'max:191'],
            'phone'             => ['nullable', 'string', 'max:32'],
            'contact_person'    => ['nullable', 'string', 'max:191'],
            'contact_phone'     => ['nullable', 'string', 'max:32'],
            'address'           => ['nullable', 'string', 'max:1000'],
            'city'              => ['nullable', 'string', 'max:100'],
            'district'          => ['nullable', 'string', 'max:100'],
            'country'           => ['nullable', 'string', 'max:100'],
            'postal_code'       => ['nullable', 'string', 'max:16'],
            'credit_limit'      => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'discount_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'         => ['nullable', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'shipping_agreement_type' => ['nullable', 'in:own,platform'],
            'carrier_id' => ['required_if:shipping_agreement_type,own', 'nullable', 'integer', 'exists:carriers,id'],
            // Ham onay bayrağı — DB kolonu değil, aşağıda shipping_terms_accepted_at'e çevrilir.
            'shipping_terms_accepted' => ['accepted_if:shipping_agreement_type,platform'],
        ], [
            'code.regex'        => 'Tenant kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
            'owner_email.unique'=> 'Bu e-posta adresiyle zaten bir kullanıcı hesabı var.',
            'carrier_id.required_if' => 'Bayi kendi kargo anlaşmasını kullanıyorsa kargo firması seçilmelidir.',
            'shipping_terms_accepted.accepted_if' => 'Platform kargo anlaşması seçildiyse şartların kabul edildiği onaylanmalı.',
        ]);
    }

    /**
     * `shipping_terms_accepted` ham onay bayrağını `shipping_terms_accepted_at`
     * damgasına çevirir; `own` seçilmişse ya da alan hiç seçilmemişse temizler.
     * Personel bu kutuyu, elinde SaaS-side imzalı/kabul edilmiş bir onay
     * olduğunu beyan ederek işaretler (bkz. proje planı: staff-checkbox yeterli).
     */
    private function applyShippingAgreementTimestamp(array $data, ?Tenant $tenant): array
    {
        $accepted = (bool) ($data['shipping_terms_accepted'] ?? false);
        unset($data['shipping_terms_accepted']);

        if (($data['shipping_agreement_type'] ?? null) !== 'platform') {
            $data['shipping_terms_accepted_at'] = null;

            return $data;
        }

        $data['shipping_terms_accepted_at'] = $accepted
            ? ($tenant?->shipping_terms_accepted_at ?? now())
            : null;

        return $data;
    }
}
