<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantType;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Tenant::query()
            ->with('type:id,code,name')
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

        return Inertia::render('Tenant::Tenants', [
            'tenants' => $tenants,
            'types'   => $types,
            'filters' => [
                'q'       => $search ?? '',
                'type_id' => $typeId ?? '',
                'active'  => $active ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tenant::create($this->validateTenant($request));

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant eklendi.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($this->validateTenant($request, $tenant->id));

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant güncellendi.');
    }

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'is_active'    => ! $tenant->is_active,
            'activated_at' => $tenant->is_active ? $tenant->activated_at : now(),
        ]);

        return back()->with(
            'success',
            $tenant->is_active ? 'Tenant aktifleştirildi.' : 'Tenant pasifleştirildi.',
        );
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if ($tenant->users()->exists()) {
            return back()->withErrors([
                'tenant' => 'Bu tenant\'a bağlı kullanıcılar var, önce kullanıcıları başka tenant\'a taşıyın.',
            ]);
        }

        $tenant->delete();

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant silindi.');
    }

    private function validateTenant(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('tenants', 'code')->ignore($ignoreId),
            ],
            'name'              => ['required', 'string', 'max:191'],
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
        ], [
            'code.regex' => 'Tenant kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        ]);
    }
}
