<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\TenantType;

class TenantTypeController extends Controller
{
    public function index(): Response
    {
        $types = TenantType::query()
            ->withCount('tenants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TenantType $t) => [
                'id'              => $t->id,
                'code'            => $t->code,
                'name'            => $t->name,
                'description'     => $t->description,
                'price_list_type' => $t->price_list_type,
                'is_active'       => $t->is_active,
                'sort_order'      => $t->sort_order,
                'tenantCount'     => $t->tenants_count,
                'updatedAt'       => optional($t->updated_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Tenant::TenantTypes', [
            'types' => $types,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TenantType::create($this->validateType($request));

        return redirect()->route('tenants.types.index')
            ->with('success', 'Tenant tipi eklendi.');
    }

    public function update(Request $request, TenantType $type): RedirectResponse
    {
        $type->update($this->validateType($request, $type->id));

        return redirect()->route('tenants.types.index')
            ->with('success', 'Tenant tipi güncellendi.');
    }

    public function destroy(TenantType $type): RedirectResponse
    {
        if ($type->tenants()->exists()) {
            return back()->withErrors([
                'type' => 'Bu tipe bağlı tenant kayıtları var, önce tenant tipini değiştirin.',
            ]);
        }

        $type->delete();

        return redirect()->route('tenants.types.index')
            ->with('success', 'Tenant tipi silindi.');
    }

    private function validateType(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('tenant_types', 'code')->ignore($ignoreId),
            ],
            'name'            => ['required', 'string', 'max:191'],
            'description'     => ['nullable', 'string', 'max:500'],
            'price_list_type' => ['nullable', Rule::in([
                TenantType::PRICE_RETAIL,
                TenantType::PRICE_DEALER,
                TenantType::PRICE_DROPSHIP,
            ])],
            'is_active'       => ['nullable', 'boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ], [
            'code.regex' => 'Tip kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        ]);
    }
}
