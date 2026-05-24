<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductFavorite;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        // Tenant kullanıcılar için erişebildiği ürünleri filtrele.
        // Superadmin ve tenant_id'siz iç kullanıcılar tüm kataloğu görür.
        $tenantId = (! $user->isSuperadmin() && $user->tenant_id) ? $user->tenant_id : null;

        $productCollection = Product::query()
            ->accessibleToTenant($tenantId)
            ->with([
                'category:id,name,slug',
                'brand:id,slug,name',
                'variants',
            ])
            ->withSum('variants as variants_total_stock', 'stock')
            ->orderByDesc('is_new')
            ->orderByDesc('id')
            ->get();

        // Tenant için fiyat lookup'larını tek seferde topla (N+1 önle).
        [$customPriceByProduct, $priceListByVariant] = $this->loadTenantPricing($tenantId, $productCollection);

        $products = $productCollection->map(function (Product $p) use ($customPriceByProduct, $priceListByVariant) {
                $sizes = $p->variants->pluck('size')->filter()->unique()->values()->all();

                $colors = $p->variants
                    ->filter(fn ($v) => $v->color_name && $v->color_hex)
                    ->unique('color_name')
                    ->map(fn ($v) => ['name' => $v->color_name, 'hex' => $v->color_hex])
                    ->values()
                    ->all();

                $customPrice = $customPriceByProduct[$p->id] ?? null;
                $variantsArr = $p->variants->map(function ($v) use ($customPrice, $priceListByVariant) {
                    $resolved = $customPrice
                        ?? ($priceListByVariant[$v->id] ?? null)
                        ?? (float) $v->price;
                    return [
                        'id'         => $v->id,
                        'size'       => $v->size,
                        'color_name' => $v->color_name,
                        'color_hex'  => $v->color_hex,
                        'sku'        => $v->sku,
                        'price'      => (float) $resolved,
                        'old_price'  => $v->old_price !== null ? (float) $v->old_price : null,
                        'stock'      => (int) $v->stock,
                    ];
                })->values()->all();

                // Ana product.price: en düşük varyant fiyatı (tenant'a göre çözülmüş).
                $displayPrice = $variantsArr === []
                    ? (float) ($customPrice ?? $p->price)
                    : (float) min(array_column($variantsArr, 'price'));

                return [
                    'id'                => $p->id,
                    'name'              => $p->name,
                    'slug'              => $p->slug,
                    'sku'               => $p->sku,
                    'category_id'       => $p->category_id,
                    'brand_id'          => $p->brand_id,
                    'brand'             => $p->brand?->slug,
                    'brandLabel'        => $p->brand?->name,
                    'category'          => $p->category?->name,
                    'categorySlug'      => $p->category?->slug,
                    'gender'            => $p->gender,
                    'price'             => $displayPrice,
                    'oldPrice'          => $p->old_price !== null ? (float) $p->old_price : null,
                    'stock'             => (int) ($p->variants_total_stock ?? 0),
                    'rating'            => (float) $p->rating,
                    'reviewCount'       => $p->review_count,
                    'isNew'             => $p->is_new,
                    'freeShipping'      => $p->free_shipping,
                    'careInstructions'  => $p->care_instructions,
                    'material'          => $p->material,
                    'originCountry'     => $p->origin_country,
                    'sizes'             => $sizes,
                    'colors'            => $colors,
                    'variants'          => $variantsArr,
                ];
            });

        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'icon'])
            ->map(fn (Category $c) => [
                'id'    => $c->id,
                'slug'  => $c->slug,
                'label' => $c->name,
                'name'  => $c->name,
                'icon'  => $c->icon ?? '📦',
            ]);

        $brands = Brand::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (Brand $b) => [
                'id'    => $b->id,
                'slug'  => $b->slug,
                'label' => $b->name,
                'name'  => $b->name,
            ]);

        $favoriteIds = ProductFavorite::query()
            ->where('user_id', $request->user()->id)
            ->pluck('product_id')
            ->all();

        return Inertia::render('Product::Products', [
            'products'    => $products,
            'categories'  => $categories,
            'brands'      => $brands,
            'favoriteIds' => $favoriteIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);

        DB::transaction(function () use ($data) {
            $product = Product::create([
                'category_id'       => $data['category_id'],
                'brand_id'          => $data['brand_id'] ?? null,
                'name'              => $data['name'],
                'sku'               => $data['sku'],
                'gender'            => $data['gender'],
                'price'             => $this->minPrice($data['variants']),
                'old_price'         => $this->minOldPrice($data['variants']),
                'is_new'            => $data['is_new'] ?? false,
                'free_shipping'     => $data['free_shipping'] ?? false,
                'care_instructions' => $data['care_instructions'] ?? null,
                'material'          => $data['material'] ?? null,
                'origin_country'    => $data['origin_country'] ?? 'TR',
            ]);

            $this->syncVariants($product, $data['variants']);
        });

        return redirect()->route('products.index')
            ->with('success', 'Ürün eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($product, $data) {
            $product->update([
                'category_id'       => $data['category_id'],
                'brand_id'          => $data['brand_id'] ?? null,
                'name'              => $data['name'],
                'sku'               => $data['sku'],
                'gender'            => $data['gender'],
                'price'             => $this->minPrice($data['variants']),
                'old_price'         => $this->minOldPrice($data['variants']),
                'is_new'            => $data['is_new'] ?? false,
                'free_shipping'     => $data['free_shipping'] ?? false,
                'care_instructions' => $data['care_instructions'] ?? null,
                'material'          => $data['material'] ?? null,
                'origin_country'    => $data['origin_country'] ?? 'TR',
            ]);

            $this->syncVariants($product, $data['variants']);
        });

        return redirect()->route('products.index')
            ->with('success', 'Ürün güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Ürün silindi.');
    }

    public function show(Request $request, Product $product): Response
    {
        $user = $request->user();
        $tenantId = (! $user->isSuperadmin() && $user->tenant_id) ? $user->tenant_id : null;

        // Tenant kapalı ürüne erişmeye çalışırsa 404 — slug var ama görmemeli.
        if ($tenantId !== null) {
            $accessible = Product::query()
                ->accessibleToTenant($tenantId)
                ->whereKey($product->id)
                ->exists();

            abort_unless($accessible, 404);
        }

        $product->load([
            'category:id,name,slug',
            'brand:id,slug,name',
            'variants',
        ])->loadSum('variants as variants_total_stock', 'stock');

        $sizes = $product->variants->pluck('size')->filter()->unique()->values()->all();

        $colors = $product->variants
            ->filter(fn ($v) => $v->color_name && $v->color_hex)
            ->unique('color_name')
            ->map(fn ($v) => ['name' => $v->color_name, 'hex' => $v->color_hex])
            ->values()
            ->all();

        $specs = array_filter([
            'Marka'    => $product->brand?->name,
            'Kategori' => $product->category?->name,
            'Cinsiyet' => $product->gender,
            'SKU'      => $product->sku,
            'Bedenler' => $sizes ? implode(', ', $sizes) : null,
            'Renkler'  => $colors ? collect($colors)->pluck('name')->implode(', ') : null,
        ]);

        $similar = Product::query()
            ->accessibleToTenant($tenantId)
            ->with(['brand:id,slug,name', 'category:id,name,slug', 'variants'])
            ->withSum('variants as variants_total_stock', 'stock')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderByDesc('rating')
            ->limit(4)
            ->get();

        // Detay ürünü + benzerleri tek lookup'la fiyatlandır.
        $pricingCollection = $similar->prepend($product);
        [$customPriceByProduct, $priceListByVariant] = $this->loadTenantPricing($tenantId, $pricingCollection);

        $similarArr = $similar
            ->map(fn (Product $p) => $this->mapProductForCard($p, $customPriceByProduct, $priceListByVariant))
            ->all();

        $customPrice = $customPriceByProduct[$product->id] ?? null;
        $variantsArr = $product->variants->map(function ($v) use ($customPrice, $priceListByVariant) {
            $resolved = $customPrice
                ?? ($priceListByVariant[$v->id] ?? null)
                ?? (float) $v->price;
            return [
                'id'         => $v->id,
                'size'       => $v->size,
                'color_name' => $v->color_name,
                'color_hex'  => $v->color_hex,
                'sku'        => $v->sku,
                'price'      => (float) $resolved,
                'old_price'  => $v->old_price !== null ? (float) $v->old_price : null,
                'stock'      => (int) $v->stock,
            ];
        })->values()->all();

        $displayPrice = $variantsArr === []
            ? (float) ($customPrice ?? $product->price)
            : (float) min(array_column($variantsArr, 'price'));

        $isFavorite = ProductFavorite::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->exists();

        return Inertia::render('Product::ProductDetail', [
            'isFavorite' => $isFavorite,
            'product' => [
                'id'           => $product->id,
                'name'         => $product->name,
                'slug'         => $product->slug,
                'sku'          => $product->sku,
                'brand'        => $product->brand?->name ?? '',
                'brandSlug'    => $product->brand?->slug,
                'category'     => $product->category?->name ?? '',
                'categorySlug' => $product->category?->slug,
                'gender'       => $product->gender,
                'price'        => $displayPrice,
                'oldPrice'     => $product->old_price !== null ? (float) $product->old_price : null,
                'stock'        => (int) ($product->variants_total_stock ?? 0),
                'rating'       => (float) $product->rating,
                'reviewCount'  => $product->review_count,
                'isNew'        => (bool) $product->is_new,
                'isBestSeller' => $product->review_count >= 200,
                'freeShipping' => (bool) $product->free_shipping,
                'sizes'        => $sizes,
                'colors'       => $colors,
                'variants'     => $variantsArr,
                'description'        => '',
                'features'           => [],
                'specs'              => $specs,
                'reviews'            => [],
                'ratingDistribution' => [],
            ],
            'similar' => $similarArr,
        ]);
    }

    private function mapProductForCard(Product $p, array $customPriceByProduct = [], array $priceListByVariant = []): array
    {
        $colors = $p->variants
            ->filter(fn ($v) => $v->color_name && $v->color_hex)
            ->unique('color_name')
            ->map(fn ($v) => ['name' => $v->color_name, 'hex' => $v->color_hex])
            ->values()
            ->all();

        $customPrice = $customPriceByProduct[$p->id] ?? null;
        $variantPrices = $p->variants->map(
            fn ($v) => $customPrice ?? ($priceListByVariant[$v->id] ?? null) ?? (float) $v->price
        )->all();
        $displayPrice = $variantPrices === []
            ? (float) ($customPrice ?? $p->price)
            : (float) min($variantPrices);

        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'slug'         => $p->slug,
            'sku'          => $p->sku,
            'brand'        => $p->brand?->name,
            'brandSlug'    => $p->brand?->slug,
            'category'     => $p->category?->name,
            'categorySlug' => $p->category?->slug,
            'gender'       => $p->gender,
            'price'        => $displayPrice,
            'oldPrice'     => $p->old_price !== null ? (float) $p->old_price : null,
            'stock'        => (int) ($p->variants_total_stock ?? 0),
            'rating'       => (float) $p->rating,
            'reviewCount'  => $p->review_count,
            'isNew'        => (bool) $p->is_new,
            'freeShipping' => (bool) $p->free_shipping,
            'sizes'        => $p->variants->pluck('size')->filter()->unique()->values()->all(),
            'colors'       => $colors,
        ];
    }

    /**
     * Tenant fiyatlandırma lookup'larını batch yükle (N+1 önle).
     *
     * @return array{0: array<int,float>, 1: array<int,float>} [customPriceByProductId, priceListByVariantId]
     */
    private function loadTenantPricing(?int $tenantId, \Illuminate\Support\Collection $products): array
    {
        if ($tenantId === null || $products->isEmpty()) {
            return [[], []];
        }

        $productIds = $products->pluck('id')->all();
        $customPriceByProduct = TenantProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $productIds)
            ->whereNotNull('custom_price')
            ->pluck('custom_price', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $priceListByVariant = [];
        $tenant = Tenant::with('type')->find($tenantId);
        $priceListType = $tenant?->type?->price_list_type;

        if ($priceListType) {
            $variantIds = $products->flatMap(fn (Product $p) => $p->variants->pluck('id'))->unique()->all();
            if ($variantIds !== []) {
                $priceListByVariant = PriceList::query()
                    ->whereIn('product_variant_id', $variantIds)
                    ->where('type', $priceListType)
                    ->where('is_active', true)
                    ->pluck('price', 'product_variant_id')
                    ->map(fn ($v) => (float) $v)
                    ->all();
            }
        }

        return [$customPriceByProduct, $priceListByVariant];
    }

    private function minPrice(array $variants): float
    {
        $prices = array_column($variants, 'price');
        return $prices === [] ? 0.0 : (float) min($prices);
    }

    private function minOldPrice(array $variants): ?float
    {
        $olds = array_filter(array_column($variants, 'old_price'), fn ($v) => $v !== null && $v !== '');
        return $olds === [] ? null : (float) min($olds);
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();

        foreach ($variants as $idx => $v) {
            $product->variants()->create([
                'size'       => $v['size'] ?? null,
                'color_name' => $v['color_name'] ?? null,
                'color_hex'  => $v['color_hex'] ?? null,
                'sku'        => $v['sku'],
                'price'      => $v['price'],
                'old_price'  => $v['old_price'] ?? null,
                'stock'      => $v['stock'] ?? 0,
                'sort_order' => $idx,
            ]);
        }
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:191'],
            'sku'               => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($ignoreId),
            ],
            'category_id'       => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'brand_id'          => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'gender'            => ['required', Rule::in(['Erkek', 'Kadın', 'Unisex'])],
            'is_new'            => ['nullable', 'boolean'],
            'free_shipping'     => ['nullable', 'boolean'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'material'          => ['nullable', 'string', 'max:191'],
            'origin_country'    => ['nullable', 'string', 'size:2'],

            'variants'                  => ['required', 'array', 'min:1'],
            'variants.*.size'           => ['nullable', 'string', 'max:16'],
            'variants.*.color_name'     => ['nullable', 'string', 'max:32'],
            'variants.*.color_hex'      => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'variants.*.sku'            => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.price'          => ['required', 'numeric', 'min:0'],
            'variants.*.old_price'      => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'          => ['required', 'integer', 'min:0'],
        ], [
            'variants.required' => 'En az bir varyant eklenmelidir.',
            'variants.min'      => 'En az bir varyant eklenmelidir.',
            'variants.*.sku.distinct' => 'Varyant SKU\'ları benzersiz olmalıdır.',
            'variants.*.color_hex.regex' => 'Renk kodu #RRGGBB formatında olmalıdır.',
        ]);
    }
}
