<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCatalogPresenter;
use Modules\Product\Services\ProductDisplayResolver;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;

class PortalCatalogController extends Controller
{
    public function __construct(
        private TenantAccessService $access,
        private ProductDisplayResolver $display,
        private ProductCatalogPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $search   = trim((string) $request->query('q', ''));
        $category = $request->integer('category') ?: null;
        $brand    = $request->integer('brand') ?: null;
        $sort     = (string) $request->query('sort', 'name');

        $query = Product::query()
            ->with([
                'brand:id,name',
                'category:id,name',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
            ])
            ->accessibleToTenant($tenant->id);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($category !== null) {
            $query->where('category_id', $category);
        }

        if ($brand !== null) {
            $query->where('brand_id', $brand);
        }

        $this->applySort($query, $sort);

        // Filtre + arama query string'i sayfalar arası korunur.
        $products = $query->paginate(24)->withQueryString();

        $products->through(function (Product $p) use ($tenant) {
            $display = $this->display->for($p, $tenant);
            return [
                'id'             => $p->id,
                'name'           => $display->name,
                'slug'           => $p->slug,
                'sku'            => $p->sku,
                'brand'          => $p->brand?->name,
                'category'       => $p->category?->name,
                'tenant_price'   => $this->access->priceFor($tenant, $p),
                'purchase_price' => (float) ($p->purchase_price ?? 0),
                'image'          => optional($p->images->first())->url,
                'display_source' => $display->source,
            ];
        });

        return Inertia::render('Tenant::Portal/Catalog', [
            'tenant'        => $this->tenantPayload($tenant),
            'products'      => $products,
            'filters'       => [
                'q'        => $search,
                'category' => $category,
                'brand'    => $brand,
                'sort'     => $sort,
            ],
            'filterOptions' => $this->filterOptions($tenant),
        ]);
    }

    /**
     * Sıralamayı whitelist üzerinden uygular. Fiyat sıralaması liste fiyatı
     * (products.price) üzerindendir; tenant'a özel fiyat gösterimde çözülür.
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc'  => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'newest'     => $query->orderByDesc('id'),
            default      => $query->orderBy('name'),
        };
    }

    /**
     * Filtre panelinde gösterilecek kategori/marka seçenekleri — yalnızca tenant'ın
     * erişebildiği ürünlerde geçen değerler (boş/erişilemez seçenek gösterilmez).
     *
     * @return array{categories: list<array{id:int,name:string}>, brands: list<array{id:int,name:string}>}
     */
    private function filterOptions(Tenant $tenant): array
    {
        $categoryIds = Product::query()->accessibleToTenant($tenant->id)
            ->distinct()->pluck('category_id')->filter()->all();
        $brandIds = Product::query()->accessibleToTenant($tenant->id)
            ->distinct()->pluck('brand_id')->filter()->all();

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])
            ->all();

        return ['categories' => $categories, 'brands' => $brands];
    }

    public function show(Request $request, Product $product): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        // Erişim kontrolü: scope üzerinden tekrar doğrula.
        abort_unless($this->access->canAccess($tenant, $product), 404);

        $product->load(['brand:id,name', 'category:id,name', 'variants']);
        $display = $this->display->for($product, $tenant);
        $rich    = $this->presenter->richContent($product, $tenant);
        $images  = $this->presenter->imageUrls($product);

        return Inertia::render('Tenant::Portal/CatalogProduct', [
            'tenant'  => $this->tenantPayload($tenant),
            'product' => [
                'id'             => $product->id,
                'name'           => $display->name,
                'slug'           => $product->slug,
                'sku'            => $product->sku,
                'description'    => $rich['description'],
                'features'       => $rich['features'],
                'specs'          => $rich['specs'],
                'display_source' => $display->source,
                'brand'          => $product->brand?->name,
                'category'       => $product->category?->name,
                // Gerçek ürün görselleri (kapak önce); görsel yoksa boş dizi → Vue placeholder gösterir.
                'images'         => $images,
                'image'          => $images[0] ?? null,
                'purchase_price' => (float) ($product->purchase_price ?? 0),
                'tenant_price'   => $this->access->priceFor($tenant, $product),
                'variants'       => $product->variants->map(fn ($v) => [
                    'id'           => $v->id,
                    'size'         => $v->size,
                    'color_name'   => $v->color_name,
                    'color_hex'    => $v->color_hex,
                    'stock'        => (int) $v->stock,
                    'tenant_price' => $this->access->priceFor($tenant, $product, $v),
                ])->all(),
            ],
        ]);
    }

    private function tenantPayload(Tenant $t): array
    {
        return [
            'id'   => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'slug' => $t->slug,
        ];
    }
}
