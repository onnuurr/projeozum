<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAccessRule;
use Modules\Tenant\Models\TenantProductAccess;

class TenantAccessController extends Controller
{
    public function show(Tenant $tenant): Response
    {
        $tenant->load('type:id,code,name');

        $rules = TenantAccessRule::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('scope_type')
            ->get();

        // Scope label'larını tek seferde çöz
        $brandIds = $rules->where('scope_type', TenantAccessRule::SCOPE_BRAND)->pluck('scope_id')->all();
        $categoryIds = $rules->where('scope_type', TenantAccessRule::SCOPE_CATEGORY)->pluck('scope_id')->all();

        $brandMap = Brand::whereIn('id', $brandIds)->pluck('name', 'id');
        $categoryMap = Category::whereIn('id', $categoryIds)->pluck('name', 'id');

        $ruleRows = $rules->map(fn (TenantAccessRule $r) => [
            'id'         => $r->id,
            'scope_type' => $r->scope_type,
            'scope_id'   => $r->scope_id,
            'scope_label' => $r->scope_type === TenantAccessRule::SCOPE_BRAND
                ? ($brandMap[$r->scope_id] ?? '#'.$r->scope_id)
                : ($categoryMap[$r->scope_id] ?? '#'.$r->scope_id),
            'is_blocked' => $r->is_blocked,
            'notes'      => $r->notes,
        ]);

        $overrides = TenantProductAccess::query()
            ->where('tenant_id', $tenant->id)
            ->with(['product:id,name,slug,sku,brand_id,category_id,price', 'product.brand:id,name', 'product.category:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TenantProductAccess $a) => [
                'id'           => $a->id,
                'product_id'   => $a->product_id,
                'product_name' => $a->product?->name,
                'product_slug' => $a->product?->slug,
                'product_sku'  => $a->product?->sku,
                'brand_name'   => $a->product?->brand?->name,
                'category_name'=> $a->product?->category?->name,
                'base_price'   => (float) ($a->product?->price ?? 0),
                'default_public_name'        => $a->product?->public_name,
                'default_tenant_description' => $a->product?->tenant_description,
                'is_blocked'         => $a->is_blocked,
                'custom_price'       => $a->custom_price !== null ? (float) $a->custom_price : null,
                'custom_name'        => $a->custom_name,
                'custom_description' => $a->custom_description,
                'notes'              => $a->notes,
            ]);

        $brands = Brand::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]);

        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Tenant::TenantAccess', [
            'tenant' => [
                'id'    => $tenant->id,
                'code'  => $tenant->code,
                'name'  => $tenant->name,
                'type'  => $tenant->type ? ['id' => $tenant->type->id, 'name' => $tenant->type->name] : null,
            ],
            'rules'      => $ruleRows,
            'overrides'  => $overrides,
            'brands'     => $brands,
            'categories' => $categories,
            'canCustomizeCopy' => (bool) request()->user()?->can('tenant.product.customize'),
        ]);
    }

    public function storeRule(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in([TenantAccessRule::SCOPE_BRAND, TenantAccessRule::SCOPE_CATEGORY])],
            'scope_id'   => ['required', 'integer'],
            'is_blocked' => ['nullable', 'boolean'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        // scope_id'nin ilgili tabloda var olduğunu doğrula
        $exists = $data['scope_type'] === TenantAccessRule::SCOPE_BRAND
            ? Brand::whereKey($data['scope_id'])->exists()
            : Category::whereKey($data['scope_id'])->exists();

        if (! $exists) {
            return back()->withErrors(['scope_id' => 'Seçilen kayıt bulunamadı.']);
        }

        TenantAccessRule::updateOrCreate(
            [
                'tenant_id'  => $tenant->id,
                'scope_type' => $data['scope_type'],
                'scope_id'   => $data['scope_id'],
            ],
            [
                'is_blocked' => $data['is_blocked'] ?? true,
                'notes'      => $data['notes'] ?? null,
            ],
        );

        return back()->with('success', 'Erişim kuralı eklendi.');
    }

    public function destroyRule(Tenant $tenant, TenantAccessRule $rule): RedirectResponse
    {
        abort_if($rule->tenant_id !== $tenant->id, 404);

        $rule->delete();

        return back()->with('success', 'Erişim kuralı silindi.');
    }

    public function storeOverride(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'is_blocked'   => ['nullable', 'boolean'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        TenantProductAccess::updateOrCreate(
            [
                'tenant_id'  => $tenant->id,
                'product_id' => $data['product_id'],
            ],
            [
                'is_blocked'   => $data['is_blocked'] ?? false,
                'custom_price' => $data['custom_price'] ?? null,
                'notes'        => $data['notes'] ?? null,
            ],
        );

        return back()->with('success', 'Ürün override\'ı kaydedildi.');
    }

    public function updateOverride(Request $request, Tenant $tenant, TenantProductAccess $access): RedirectResponse
    {
        abort_if($access->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'is_blocked'   => ['nullable', 'boolean'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $access->update([
            'is_blocked'   => $data['is_blocked'] ?? $access->is_blocked,
            'custom_price' => array_key_exists('custom_price', $data) ? $data['custom_price'] : $access->custom_price,
            'notes'        => array_key_exists('notes', $data) ? $data['notes'] : $access->notes,
        ]);

        return back()->with('success', 'Override güncellendi.');
    }

    /**
     * Bir bayi için ürün özel adı ve açıklamasını günceller (M3).
     */
    public function updateCustomCopy(Request $request, Tenant $tenant, TenantProductAccess $access): RedirectResponse
    {
        abort_if($access->tenant_id !== $tenant->id, 404);
        abort_unless($request->user()?->can('tenant.product.customize'), 403);

        $data = $request->validate([
            'custom_name'        => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:10000'],
        ]);

        $access->update([
            'custom_name'        => $data['custom_name'] ?: null,
            'custom_description' => $data['custom_description'] ?: null,
        ]);

        return back()->with('success', 'Bayiye özel metin güncellendi.');
    }

    public function destroyOverride(Tenant $tenant, TenantProductAccess $access): RedirectResponse
    {
        abort_if($access->tenant_id !== $tenant->id, 404);

        $access->delete();

        return back()->with('success', 'Override silindi.');
    }

    /**
     * Ürün override ekleme modalında autocomplete için.
     */
    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'slug', 'sku', 'brand_id', 'category_id', 'price'])
            ->map(fn (Product $p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'sku'           => $p->sku,
                'slug'          => $p->slug,
                'brand_name'    => $p->brand?->name,
                'category_name' => $p->category?->name,
                'price'         => (float) $p->price,
            ]);

        return response()->json(['products' => $products]);
    }
}
