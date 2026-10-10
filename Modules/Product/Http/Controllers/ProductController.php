<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Exceptions\InvalidProductTransitionException;
use Modules\Product\Exceptions\VariantInUseException;
use Modules\Product\Http\Requests\BulkDestroyProductRequest;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Product\Http\Requests\UpdateProductStatusRequest;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Product\Services\ProductAttributeService;
use Modules\Product\Services\ProductCatalogPresenter;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\VatRateService;
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
                'images' => fn ($q) => $q->reorder()->orderByDesc('is_cover')->orderBy('sort_order'),
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
            'marketplaces' => $marketplaces,
            // "Üretilecek Ürün" akışı Atölye'dedir (Product → Atelier bağımlılığı yasak);
            // yalnız route adı + yetkiyle kontrol edilir, kod bağımlılığı kurulmaz.
            'canAddManufactured' => Route::has('atelier.development-products.create')
                && $user->can('product.add')
                && $user->can('atelier.bom.manage'),
        ]);
    }

    /**
     * Yeni ürün ekleme formu (tam sayfa). Drawer yerine ayrı Inertia sayfası.
     */
    public function create(): Response
    {
        [$categories, $brands] = $this->formReferenceData();

        return Inertia::render('Product::ProductForm', [
            'product'              => null,
            'categories'           => $categories,
            'brands'               => $brands,
            'attributeDefinitions' => app(ProductAttributeService::class)->formDefinitions(),
            'sizeOptions'          => ProductSize::query()->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'colorOptions'         => ProductColor::query()->orderBy('sort_order')->orderBy('name')->get(['name', 'hex']),
            ...$this->vatFormData(),
        ]);
    }

    /**
     * Ürün düzenleme formu (tam sayfa).
     */
    public function edit(Product $product): Response
    {
        $product->load([
            'variants' => fn ($q) => $q->orderBy('sort_order'),
            'images'   => fn ($q) => $q->reorder()->orderByDesc('is_cover')->orderBy('sort_order'),
            'descriptionMaterials.material:id,code,name,type,unit,specs',
        ]);

        [$categories, $brands] = $this->formReferenceData();

        return Inertia::render('Product::ProductForm', [
            'product'              => $this->shapeProductForForm($product),
            'categories'           => $categories,
            'brands'               => $brands,
            'attributeDefinitions' => app(ProductAttributeService::class)->formDefinitions(),
            'sizeOptions'          => ProductSize::query()->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'colorOptions'         => ProductColor::query()->orderBy('sort_order')->orderBy('name')->get(['name', 'hex']),
            ...$this->vatFormData(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        // Yeni ürün pasif (Taslak) başlar: işlemleri bitince Ürün Kartı'ndan yayınlanır; Bagisto'ya
        // ilk yayında gider (bkz. PushProductToBagisto, Product::hasBeenPublished).
        $this->products->create($request->validated(), $request->file('images') ?? [], Product::STATUS_DRAFT);

        // Flash basılmıyor — ProductForm.vue onSuccess'te kendi toast'unu gösteriyor.
        return redirect()->route('products.index');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->products->update($product, $request->validated(), $request->file('images') ?? []);
        } catch (VariantInUseException $e) {
            // Transaction geri alındı; formdaki varyant listesine hata olarak döner.
            return back()->withErrors(['variants' => $e->getMessage()]);
        }

        return redirect()->route('products.index');
    }

    public function updateStatus(UpdateProductStatusRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->products->transitionStatus(
                $product,
                $request->validated('status'),
                $request->user(),
                $request->validated('note'),
            );
        } catch (InvalidProductTransitionException $e) {
            // withErrors() kullanılıyor ki Inertia bunu bir 422 validation hatası gibi
            // ele alıp frontend'in onError callback'ini tetiklesin (bkz. OrderController::updateStatus).
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        // Flash basılmıyor — Products.vue onSuccess'te kendi toast'unu gösteriyor.
        return redirect()->route('products.index');
    }

    public function bulkDestroy(BulkDestroyProductRequest $request): RedirectResponse
    {
        $count = $this->products->bulkDelete($request->validated()['ids']);

        return redirect()->route('products.index');
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
                'images' => fn ($q) => $q->reorder()->orderByDesc('is_cover')->orderBy('sort_order'),
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

        return Inertia::render('Product::ProductDetail', [
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
        // Durum otomatik (yayında ürünü olmayan kategori pasif, CategoryStatusService) — "aktif" filtresi
        // boş kategoriye ilk ürünün eklenmesini engellerdi. Yalnız bilerek kapatılanlar (elle pasif) gizlenir.
        // Etiket tam yol: iki ana kategoride aynı adlı alt kategoriler var (Kız Çocuk › Tişört).
        $all = Category::query()->get(['id', 'parent_id', 'slug', 'name', 'icon', 'sort_order', 'manually_passive'])->keyBy('id');
        $path = function (Category $c) use ($all): string {
            $names = [$c->name];
            $seen = [$c->id => true];
            for ($p = $all->get($c->parent_id); $p && ! isset($seen[$p->id]); $p = $all->get($p->parent_id)) {
                $seen[$p->id] = true;
                array_unshift($names, $p->name);
            }

            return implode(' › ', $names);
        };

        $categories = $all
            ->reject(fn (Category $c) => $c->manually_passive)
            ->map(fn (Category $c) => [
                'id'    => $c->id,
                'slug'  => $c->slug,
                'label' => $path($c),
                'name'  => $path($c),
                'icon'  => $c->icon ?? '📦',
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

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
     * Fiyat sekmesindeki KDV seçimi: `kdv_orani` sistem özelliğinin seçenekleri ve boş bırakılınca
     * geçerli olacak kategori oranları (miras çözülmüş).
     *
     * @return array{vatOptions: list<array{value: int, label: string, rate: float}>, categoryVatRates: array<int, float>}
     */
    private function vatFormData(): array
    {
        $vat = app(VatRateService::class);

        return [
            'vatOptions'       => $vat->options(),
            'categoryVatRates' => Category::query()->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => $vat->rateForCategory($id)])
                ->all(),
        ];
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
            'marketPrice'      => $product->market_price !== null ? (float) $product->market_price : null,
            'purchasePrice'    => $product->purchase_price !== null ? (float) $product->purchase_price : null,
            'maxOrderQty'      => $product->max_order_qty,
            'isNew'            => (bool) $product->is_new,
            'isFeatured'       => (bool) $product->is_featured,
            'freeShipping'     => (bool) $product->free_shipping,
            'material'         => $product->material,
            'attributes'       => $product->attributes ?? [],
            // Özellik görsel/dosya değerlerinin önizleme adresleri (değerin kendisi relative path)
            'attributeUrls'    => collect($product->attributes ?? [])
                ->filter(fn ($v) => is_string($v) && str_starts_with($v, ProductAttribute::UPLOAD_DIR.'/'))
                ->map(fn (string $v) => Media::url($v))
                ->all(),
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
            'length_cm'        => $product->length_cm !== null ? (float) $product->length_cm : null,
            'width_cm'         => $product->width_cm !== null ? (float) $product->width_cm : null,
            'height_cm'        => $product->height_cm !== null ? (float) $product->height_cm : null,
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
                // Yalın fotoğrafın rengi (null = bütün renkler) — görsel kartındaki renk seçimi.
                'variant_colors' => $img->variant_colors,
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
