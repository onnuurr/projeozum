<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Carrier;
use Modules\Tenant\Http\Requests\SaveTenantRequest;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantType;
use Modules\Tenant\Services\TenantApplicationService;
use Modules\Tenant\Services\TenantService;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $service,
        private TenantApplicationService $applications,
    ) {}

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
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'legal_name' => $t->legal_name,
                'type' => $t->type ? [
                    'id' => $t->type->id,
                    'code' => $t->type->code,
                    'name' => $t->type->name,
                ] : null,
                'owner' => $t->owner ? [
                    'id' => $t->owner->id,
                    'name' => $t->owner->name,
                    'email' => $t->owner->email,
                ] : null,
                'tax_number' => $t->tax_number,
                'tax_office' => $t->tax_office,
                'email' => $t->email,
                'phone' => $t->phone,
                'contact_person' => $t->contact_person,
                'contact_phone' => $t->contact_phone,
                'address' => $t->address,
                'city' => $t->city,
                'district' => $t->district,
                'country' => $t->country,
                'postal_code' => $t->postal_code,
                'credit_limit' => (float) $t->credit_limit,
                'current_balance' => (float) $t->current_balance,
                'available_credit' => $t->available_credit,
                'shipping_agreement_type' => $t->shipping_agreement_type,
                'shipping_terms_accepted_at' => optional($t->shipping_terms_accepted_at)->format('Y-m-d H:i'),
                'carrier' => $t->carrier ? [
                    'id' => $t->carrier->id,
                    'code' => $t->carrier->code,
                    'name' => $t->carrier->name,
                ] : null,
                'payment_term_days' => $t->payment_term_days,
                'discount_rate' => (float) $t->discount_rate,
                'is_active' => $t->is_active,
                'notes' => $t->notes,
                'userCount' => $t->users_count,
                'updatedAt' => optional($t->updated_at)->format('Y-m-d'),
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
            'tenants' => $tenants,
            'types' => $types,
            'carriers' => $carriers,
            'filters' => [
                'q' => $search ?? '',
                'type_id' => $typeId ?? '',
                'active' => $active ?? '',
            ],
            'prefillApplication' => $this->applications->prefillFor($request->query('from_application')),
        ]);
    }

    public function store(SaveTenantRequest $request): RedirectResponse
    {
        $tenant = $this->service->create($request->tenantData());

        if ($applicationId = $request->applicationId()) {
            $this->applications->markConverted($applicationId, $tenant);
        }

        // Flash basılmıyor — Tenants.vue onSuccess'te kendi toast'unu gösteriyor.
        return redirect()->route('tenants.index');
    }

    public function update(SaveTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->update($tenant, $request->tenantData());

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
                'tenant' => 'Bu iş ortağına bağlı kullanıcılar var, önce kullanıcıları başka iş ortağına taşıyın.',
            ]);
        }

        $tenant->delete();

        return redirect()->route('tenants.index');
    }
}
