<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductDescriptionMaterial;
use Modules\Product\Models\ProductFavorite;
use Modules\Product\Services\ProductMaterialLinkService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;

class ProductController extends Controller
{
    public function __construct(private ProductMaterialLinkService $materialLinks) {}

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
                'category.marketplaceMappings:id,category_id,marketplace_id',
                'category.marketplaceMappings.marketplace:id,key,name,color,logo_text',
                'brand:id,slug,name',
                'variants',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
                'listings.marketplace:id,key',
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
                    'image'             => optional($p->images->first())->url,
                    'images'            => $p->images->map(fn ($img) => [
                        'id'       => $img->id,
                        'url'      => $img->url,
                        'is_cover' => (bool) $img->is_cover,
                    ])->values()->all(),
                    'category_id'       => $p->category_id,
                    'brand_id'          => $p->brand_id,
                    'brand'             => $p->brand?->slug,
                    'brandLabel'        => $p->brand?->name,
                    'category'          => $p->category?->name,
                    'categorySlug'      => $p->category?->slug,
                    'gender'            => $p->gender,
                    'price'             => $displayPrice,
                    'oldPrice'          => $p->old_price !== null ? (float) $p->old_price : null,
                    'marketPrice'       => $p->market_price !== null ? (float) $p->market_price : null,
                    'purchasePrice'     => $p->purchase_price !== null ? (float) $p->purchase_price : null,
                    'barcode'           => $p->barcode,
                    'desi'              => $p->desi !== null ? (float) $p->desi : null,
                    'marketplaces'      => optional($p->category)->marketplaceMappings
                        ?->map(fn ($m) => [
                            'key'      => $m->marketplace?->key,
                            'name'     => $m->marketplace?->name,
                            'color'    => $m->marketplace?->color,
                            'logoText' => $m->marketplace?->logo_text,
                        ])->filter(fn ($x) => $x['key'])->values()->all() ?? [],
                    'listings'          => $p->listings->mapWithKeys(fn ($l) => [
                        $l->marketplace->key => [
                            'price'  => $l->price !== null ? (float) $l->price : null,
                            'isSent' => (bool) $l->is_sent,
                        ],
                    ])->all(),
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

        [$categories, $brands] = $this->formReferenceData();

        $favoriteIds = ProductFavorite::query()
            ->where('user_id', $request->user()->id)
            ->pluck('product_id')
            ->all();

        // Tüm pazaryeri listesi (test/fallback: eşleşmesi olmayan üründe de ikon göstermek için).
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->get(['key', 'name', 'color', 'logo_text'])
            ->map(fn (Marketplace $m) => [
                'key'      => $m->key,
                'name'     => $m->name,
                'color'    => $m->color,
                'logoText' => $m->logo_text,
            ]);

        return Inertia::render('Product::Products', [
            'products'     => $products,
            'categories'   => $categories,
            'brands'       => $brands,
            'favoriteIds'  => $favoriteIds,
            'marketplaces' => $marketplaces,
        ]);
    }

    /**
     * Yeni ürün ekleme formu (tam sayfa). Drawer yerine ayrı Inertia sayfası.
     */
    public function create(): Response
    {
        [$categories, $brands] = $this->formReferenceData();

        return Inertia::render('Product::ProductForm', [
            'product'    => null,
            'categories' => $categories,
            'brands'     => $brands,
        ]);
    }

    /**
     * Ürün düzenleme formu (tam sayfa).
     */
    public function edit(Product $product): Response
    {
        $product->load([
            'variants' => fn ($q) => $q->orderBy('sort_order'),
            'images'   => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
            'descriptionMaterials.material:id,code,name,type,unit,specs',
        ]);

        [$categories, $brands] = $this->formReferenceData();

        return Inertia::render('Product::ProductForm', [
            'product'    => $this->shapeProductForForm($product),
            'categories' => $categories,
            'brands'     => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);

        $product = DB::transaction(function () use ($data) {
            $product = Product::create($this->productAttributes($data));

            $this->syncVariants($product, $data['variants']);
            $this->materialLinks->syncMaterials($product, $data['description_materials'] ?? []);

            return $product;
        });

        $this->storeUploadedImages($product, $request);

        return redirect()->route('products.index')
            ->with('success', 'Ürün eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($product, $data) {
            $product->update($this->productAttributes($data));

            $this->syncVariants($product, $data['variants']);
            $this->materialLinks->syncMaterials($product, $data['description_materials'] ?? []);
        });

        $this->storeUploadedImages($product, $request);

        return redirect()->route('products.index')
            ->with('success', 'Ürün güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Ürün silindi.');
    }

    /**
     * Birden çok ürünü topluca siler (tek tek model olayları tetiklensin diye döngüyle).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        $count = 0;
        DB::transaction(function () use ($data, &$count) {
            foreach (Product::whereIn('id', $data['ids'])->get() as $product) {
                $product->delete();
                $count++;
            }
        });

        return redirect()->route('products.index')
            ->with('success', "{$count} ürün silindi.");
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
            ->with(['brand:id,slug,name', 'category:id,name,slug', 'variants', 'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order')])
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
                'images'       => $product->images()
                    ->orderByDesc('is_cover')->orderBy('sort_order')
                    ->pluck('path')->map(fn ($p) => Media::url($p))->filter()->values()->all(),
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
            'image'        => $p->relationLoaded('images') ? optional($p->images->first())->url : null,
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

    /**
     * Form sayfaları (create/edit) ve katalog için ortak kategori + marka listesi.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function formReferenceData(): array
    {
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

        return [$categories, $brands];
    }

    /**
     * Düzenleme formunun beklediği şekle ürünü dönüştürür (varyant + görsel dahil).
     */
    private function shapeProductForForm(Product $product): array
    {
        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'sku'              => $product->sku,
            'category_id'      => $product->category_id,
            'brand_id'         => $product->brand_id,
            'gender'           => $product->gender,
            'marketPrice'      => $product->market_price !== null ? (float) $product->market_price : null,
            'purchasePrice'    => $product->purchase_price !== null ? (float) $product->purchase_price : null,
            'isNew'            => (bool) $product->is_new,
            'freeShipping'     => (bool) $product->free_shipping,
            'careInstructions' => $product->care_instructions,
            'material'         => $product->material,
            'originCountry'    => $product->origin_country,
            'publicName'        => $product->public_name,
            'publicDescription' => $product->public_description,
            'tenantDescription' => $product->tenant_description,
            'aiGeneratedAt'     => $product->ai_generated_at?->toIso8601String(),
            // SEO
            'metaTitle'        => $product->meta_title,
            'metaDescription'  => $product->meta_description,
            'metaKeywords'     => $product->meta_keywords,
            // Kargo
            'weight'           => $product->weight !== null ? (float) $product->weight : null,
            'desi'             => $product->desi !== null ? (float) $product->desi : null,
            'shippingTime'     => $product->shipping_time,
            'shippingFee'      => $product->shipping_fee !== null ? (float) $product->shipping_fee : null,
            // Diğer
            'barcode'          => $product->barcode,
            'isDomestic'       => (bool) $product->is_domestic,
            'manufacturerCode' => $product->manufacturer_code,
            'gtipCode'         => $product->gtip_code,
            'images'           => $product->images->map(fn ($img) => [
                'id'       => $img->id,
                'url'      => $img->url,
                'is_cover' => (bool) $img->is_cover,
            ])->values()->all(),
            'variants'         => $product->variants->map(fn ($v) => [
                'id'         => $v->id,
                'size'       => $v->size,
                'color_name' => $v->color_name,
                'color_hex'  => $v->color_hex,
                'sku'        => $v->sku,
                'price'      => (float) $v->price,
                'old_price'  => $v->old_price !== null ? (float) $v->old_price : null,
                'stock'      => (int) $v->stock,
            ])->values()->all(),
            'description_materials' => $product->relationLoaded('descriptionMaterials')
                ? $product->descriptionMaterials->map(fn ($r) => [
                    'material_id' => $r->material_id,
                    'material'    => $r->material ? [
                        'id'    => $r->material->id,
                        'code'  => $r->material->code,
                        'name'  => $r->material->name,
                        'type'  => $r->material->type,
                        'unit'  => $r->material->unit,
                        'specs' => (array) ($r->material->specs ?? []),
                    ] : null,
                    'role'       => $r->role,
                    'sort_order' => (int) $r->sort_order,
                    'notes'      => $r->notes,
                ])->values()->all()
                : [],
        ];
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

    /**
     * Form ile yüklenen görselleri ürüne kaydeder. Üründe kapak yoksa ilk
     * yüklenen kapak olur. (Multipart: images[] dosyaları.)
     */
    private function storeUploadedImages(Product $product, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasCover = $product->images()->where('is_cover', true)->exists();
        $sort     = (int) $product->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $path = $file->store('products/' . $product->id, Media::disk());

            $product->images()->create([
                'path'       => $path,
                'sort_order' => ++$sort,
                'is_cover'   => ! $hasCover,
            ]);

            $hasCover = true;
        }
    }

    /**
     * Doğrulanmış form verisinden products tablosu attribute dizisini üretir.
     * Fiyat varyantlardan türetilir; slug boşsa model otomatik üretir.
     */
    private function productAttributes(array $data): array
    {
        return [
            'category_id'       => $data['category_id'],
            'brand_id'          => $data['brand_id'] ?? null,
            'name'              => $data['name'],
            'slug'              => $data['slug'] ?? null,
            'sku'               => $data['sku'],
            'gender'            => $data['gender'],
            'price'             => $this->minPrice($data['variants']),
            'old_price'         => $this->minOldPrice($data['variants']),
            'market_price'      => $data['market_price'] ?? null,
            'purchase_price'    => $data['purchase_price'] ?? null,
            'is_new'            => $data['is_new'] ?? false,
            'free_shipping'     => $data['free_shipping'] ?? false,
            'care_instructions' => $data['care_instructions'] ?? null,
            'material'          => $data['material'] ?? null,
            'origin_country'    => $data['origin_country'] ?? 'TR',
            'public_name'        => $data['public_name'] ?? null,
            'public_description' => $data['public_description'] ?? null,
            'tenant_description' => $data['tenant_description'] ?? null,
            // SEO
            'meta_title'        => $data['meta_title'] ?? null,
            'meta_description'  => $data['meta_description'] ?? null,
            'meta_keywords'     => $data['meta_keywords'] ?? null,
            // Kargo
            'weight'            => $data['weight'] ?? null,
            'desi'              => $data['desi'] ?? null,
            'shipping_time'     => $data['shipping_time'] ?? null,
            'shipping_fee'      => $data['shipping_fee'] ?? null,
            // Diğer
            'barcode'           => $data['barcode'] ?? null,
            'is_domestic'       => $data['is_domestic'] ?? false,
            'manufacturer_code' => $data['manufacturer_code'] ?? null,
            'gtip_code'         => $data['gtip_code'] ?? null,
        ];
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
            'slug'              => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('products', 'slug')->ignore($ignoreId),
            ],
            'category_id'       => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'brand_id'          => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'gender'            => ['required', Rule::in(['Erkek', 'Kadın', 'Unisex'])],
            'market_price'      => ['nullable', 'numeric', 'min:0'],
            'purchase_price'    => ['nullable', 'numeric', 'min:0'],
            'is_new'            => ['nullable', 'boolean'],
            'free_shipping'     => ['nullable', 'boolean'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'material'          => ['nullable', 'string', 'max:191'],
            'origin_country'    => ['nullable', 'string', 'size:2'],

            // Açıklamalar (M2)
            'public_name'        => ['nullable', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:10000'],
            'tenant_description' => ['nullable', 'string', 'max:10000'],

            // SEO
            'meta_title'        => ['nullable', 'string', 'max:191'],
            'meta_description'  => ['nullable', 'string', 'max:500'],
            'meta_keywords'     => ['nullable', 'string', 'max:255'],

            // Kargo
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'desi'              => ['nullable', 'numeric', 'min:0'],
            'shipping_time'     => ['nullable', 'string', 'max:50'],
            'shipping_fee'      => ['nullable', 'numeric', 'min:0'],

            // Diğer
            'barcode'           => ['nullable', 'string', 'max:64'],
            'is_domestic'       => ['nullable', 'boolean'],
            'manufacturer_code' => ['nullable', 'string', 'max:64'],
            'gtip_code'         => ['nullable', 'string', 'max:32'],

            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'], // her görsel 5MB (webp dahil)

            'variants'                  => ['required', 'array', 'min:1'],
            'variants.*.size'           => ['nullable', 'string', 'max:16'],
            'variants.*.color_name'     => ['nullable', 'string', 'max:32'],
            'variants.*.color_hex'      => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'variants.*.sku'            => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.price'          => ['required', 'numeric', 'min:0'],
            'variants.*.old_price'      => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'          => ['required', 'integer', 'min:0'],

            // Ürün açıklaması için materyal bağı (M1).
            'description_materials'                 => ['nullable', 'array'],
            'description_materials.*.material_id'   => ['required_with:description_materials.*', 'integer', Rule::exists('materials', 'id')],
            'description_materials.*.role'          => ['required_with:description_materials.*', Rule::in(ProductDescriptionMaterial::ROLES)],
            'description_materials.*.sort_order'    => ['nullable', 'integer', 'min:0'],
            'description_materials.*.notes'         => ['nullable', 'string', 'max:500'],
        ], [
            'variants.required' => 'En az bir varyant eklenmelidir.',
            'variants.min'      => 'En az bir varyant eklenmelidir.',
            'variants.*.sku.distinct' => 'Varyant SKU\'ları benzersiz olmalıdır.',
            'variants.*.color_hex.regex' => 'Renk kodu #RRGGBB formatında olmalıdır.',
        ]);
    }
}
