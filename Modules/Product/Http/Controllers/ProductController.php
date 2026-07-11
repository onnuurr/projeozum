<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\BulkDestroyProductRequest;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductFavorite;
use Modules\Product\Services\ProductCatalogPresenter;
use Modules\Product\Services\ProductService;
use Modules\Tenant\Models\Tenant;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $products,
        private ProductCatalogPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $user   = $request->user();
        $tenant = $this->resolveTenant($user);

        $productCollection = Product::query()
            ->accessibleToTenant($tenant?->id)
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

        // Tenant fiyat lookup'larını tek seferde topla (N+1 önle).
        [$customPriceByProduct, $priceListByVariant] = $this->presenter->loadTenantPricing($tenant, $productCollection);

        $products = $productCollection
            ->map(fn (Product $p) => $this->presenter->catalogRow($p, $customPriceByProduct, $priceListByVariant));

        [$categories, $brands] = $this->formReferenceData();

        $favoriteIds = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->all();

        // Tüm pazaryeri listesi (eşleşmesi olmayan üründe de ikon göstermek için).
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

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->products->create($request->validated(), $request->file('images') ?? []);

        return redirect()->route('products.index')
            ->with('success', 'Ürün eklendi.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->validated(), $request->file('images') ?? []);

        return redirect()->route('products.index')
            ->with('success', 'Ürün güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('products.index')
            ->with('success', 'Ürün silindi.');
    }

    public function bulkDestroy(BulkDestroyProductRequest $request): RedirectResponse
    {
        $count = $this->products->bulkDelete($request->validated()['ids']);

        return redirect()->route('products.index')
            ->with('success', "{$count} ürün silindi.");
    }

    public function show(Request $request, Product $product): Response
    {
        $user   = $request->user();
        $tenant = $this->resolveTenant($user);

        // Tenant kapalı ürüne erişmeye çalışırsa 404 — slug var ama görmemeli.
        if ($tenant !== null) {
            $accessible = Product::query()
                ->accessibleToTenant($tenant->id)
                ->whereKey($product->id)
                ->exists();

            abort_unless($accessible, 404);
        }

        $product->load([
            'category:id,name,slug',
            'brand:id,slug,name',
            'variants',
        ])->loadSum('variants as variants_total_stock', 'stock');

        $similar = Product::query()
            ->accessibleToTenant($tenant?->id)
            ->with([
                'brand:id,slug,name',
                'category:id,name,slug',
                'variants',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
            ])
            ->withSum('variants as variants_total_stock', 'stock')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderByDesc('rating')
            ->limit(4)
            ->get();

        // Detay ürünü + benzerleri tek lookup'la fiyatlandır.
        $pricingCollection = $similar->prepend($product);
        [$customPriceByProduct, $priceListByVariant] = $this->presenter->loadTenantPricing($tenant, $pricingCollection);

        $isFavorite = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        return Inertia::render('Product::ProductDetail', [
            'isFavorite' => $isFavorite,
            'product'    => $this->presenter->detail($product, $tenant, $customPriceByProduct, $priceListByVariant),
            'similar'    => $similar
                ->map(fn (Product $p) => $this->presenter->card($p, $customPriceByProduct, $priceListByVariant))
                ->all(),
        ]);
    }

    /**
     * İç kullanıcı/superadmin tüm kataloğu görür; tenant kullanıcısı yalnızca
     * erişebildiği ürünleri. Fiyat listesi + açıklama override'ı için tenant
     * modeli (type ilişkisiyle) yüklenir.
     */
    private function resolveTenant(User $user): ?Tenant
    {
        if ($user->isSuperadmin() || ! $user->tenant_id) {
            return null;
        }

        return Tenant::with('type')->find($user->tenant_id);
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
}
