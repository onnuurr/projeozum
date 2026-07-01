<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductDisplayResolver;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;

class PortalCatalogController extends Controller
{
    public function __construct(
        private TenantAccessService $access,
        private ProductDisplayResolver $display,
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $search = trim((string) $request->query('q', ''));

        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->accessibleToTenant($tenant->id);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(24);

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
                'image'          => "https://picsum.photos/seed/tek-p{$p->id}/300/400",
                'display_source' => $display->source,
            ];
        });

        return Inertia::render('Tenant::Portal/Catalog', [
            'tenant'   => $this->tenantPayload($tenant),
            'products' => $products,
            'filters'  => ['q' => $search],
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        // Erişim kontrolü: scope üzerinden tekrar doğrula.
        abort_unless($this->access->canAccess($tenant, $product), 404);

        $product->load(['brand:id,name', 'category:id,name', 'variants']);
        $display = $this->display->for($product, $tenant);

        return Inertia::render('Tenant::Portal/CatalogProduct', [
            'tenant'  => $this->tenantPayload($tenant),
            'product' => [
                'id'             => $product->id,
                'name'           => $display->name,
                'slug'           => $product->slug,
                'sku'            => $product->sku,
                'description'    => $display->description,
                'display_source' => $display->source,
                'brand'          => $product->brand?->name,
                'category'       => $product->category?->name,
                'image'          => "https://picsum.photos/seed/tek-p{$product->id}/600/800",
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
