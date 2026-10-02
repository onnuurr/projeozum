<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;

/**
 * SAP MM03 (malzeme görüntüleme) benzeri "Ürün Kartı": ürüne ait TÜM bilgiyi
 * (temel veri, stok, fiyat, pazaryeri listeleme) tek sayfada sekmeler halinde
 * toplar. `Product` (storefront `ProductController::show`) satışa sunulan
 * görünüm — bu kart, o görünümü besleyen master veridir.
 *
 * Yazma aksiyonları burada tekrar edilmez: stok hareketi StockController,
 * fiyat PriceListController, pazaryeri ProductMarketplaceListingController
 * üzerinden — her biri kendi yetkisiyle (stock.manage / price-list.manage /
 * product.add) korunur. Bu controller salt-okuma agregasyon yapar.
 */
class ProductCardController extends Controller
{
    public function show(Request $request, Product $product): Response
    {
        $product->load([
            'category:id,name',
            'category.marketplaceMappings:id,category_id,marketplace_id',
            'category.marketplaceMappings.marketplace:id,key,name,color,logo_text',
            'brand:id,name',
            'listings.marketplace:id,key',
        ]);

        $images = $product->images()
            ->reorder()
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($img) => ['id' => $img->id, 'url' => $img->url, 'isCover' => $img->is_cover]);

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $variants = $product->variants()->with(['stocks', 'priceLists'])->get();
        $variantIds = $variants->pluck('id');

        $variantRows = $variants->map(function (ProductVariant $v) use ($warehouses) {
            $byWarehouse = $v->stocks->keyBy('warehouse_id');

            return [
                'id'         => $v->id,
                'size'       => $v->size,
                'colorName'  => $v->color_name,
                'colorHex'   => $v->color_hex,
                'sku'        => $v->sku,
                'totalStock' => $v->stock,
                'basePrice'  => (float) $v->price,
                'oldPrice'   => $v->old_price !== null ? (float) $v->old_price : null,
                'warehouses' => $warehouses->map(function (Warehouse $w) use ($byWarehouse) {
                    $s = $byWarehouse->get($w->id);

                    return [
                        'warehouseId' => $w->id,
                        'quantity'    => $s?->quantity ?? 0,
                        'reserved'    => $s?->reserved_quantity ?? 0,
                        'available'   => $s?->available_quantity ?? 0,
                        'minQuantity' => $s?->min_quantity ?? 0,
                        'isCritical'  => $s ? $s->quantity <= $s->min_quantity : false,
                    ];
                })->values(),
                'priceLists' => $v->priceLists->map(fn ($pl) => [
                    'id'       => $pl->id,
                    'type'     => $pl->type,
                    'price'    => (float) $pl->price,
                    'currency' => $pl->currency,
                    'isActive' => $pl->is_active,
                ])->values(),
            ];
        });

        $stockRows = Stock::query()->whereIn('product_variant_id', $variantIds)->get(['quantity', 'reserved_quantity']);

        $totals = [
            'stock'            => (int) $variants->sum('stock'),
            'reserved'         => (int) $stockRows->sum('reserved_quantity'),
            'available'        => (int) $stockRows->sum(fn (Stock $s) => max(0, $s->quantity - $s->reserved_quantity)),
            'criticalVariants' => $variantRows->filter(
                fn (array $v) => collect($v['warehouses'])->contains('isCritical', true)
            )->count(),
        ];

        $movements = StockMovement::query()
            ->whereIn('product_variant_id', $variantIds)
            ->with([
                'variant:id,size,color_name,sku',
                'warehouse:id,name,code',
                'user:id,name',
            ])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id'             => $m->id,
                'type'           => $m->type,
                'quantity'       => $m->quantity,
                'beforeQuantity' => $m->before_quantity,
                'afterQuantity'  => $m->after_quantity,
                'note'           => $m->note,
                'size'           => $m->variant?->size,
                'colorName'      => $m->variant?->color_name,
                'sku'            => $m->variant?->sku,
                'warehouseName'  => $m->warehouse?->name,
                'warehouseCode'  => $m->warehouse?->code,
                'userName'       => $m->user?->name,
                'createdAt'      => optional($m->created_at)->format('Y-m-d H:i'),
            ]);

        // Kategorinin bağlı olduğu pazaryerleri (eşleşmesi olmayanı da göster ki
        // kullanıcı yeni bir listeleme başlatabilsin) + üründe var olan listeleme özeti.
        $marketplaces = optional($product->category)->marketplaceMappings
            ?->map(fn ($m) => [
                'key'      => $m->marketplace?->key,
                'name'     => $m->marketplace?->name,
                'color'    => $m->marketplace?->color,
                'logoText' => $m->marketplace?->logo_text,
            ])->filter(fn ($x) => $x['key'])->values()->all() ?? [];

        $listings = $product->listings->mapWithKeys(fn ($l) => [
            $l->marketplace->key => [
                'price'  => $l->price !== null ? (float) $l->price : null,
                'isSent' => (bool) $l->is_sent,
            ],
        ])->all();

        return Inertia::render('Product::ProductCard', [
            'initialTab' => $request->string('tab')->toString() ?: 'genel',
            'product'    => [
                'id'               => $product->id,
                'name'             => $product->name,
                'slug'             => $product->slug,
                'status'           => $product->status,
                'sku'              => $product->sku,
                'barcode'          => $product->barcode,
                'gender'           => $product->gender,
                'category'         => $product->category?->name,
                'brand'            => $product->brand?->name,
                'price'            => (float) $product->price,
                'oldPrice'         => $product->old_price !== null ? (float) $product->old_price : null,
                'marketPrice'      => $product->market_price !== null ? (float) $product->market_price : null,
                'purchasePrice'    => $product->purchase_price !== null ? (float) $product->purchase_price : null,
                'manufacturerCode' => $product->manufacturer_code,
                'gtipCode'         => $product->gtip_code,
                'originCountry'    => $product->origin_country,
                'isDomestic'       => $product->is_domestic,
                'weight'           => $product->weight !== null ? (float) $product->weight : null,
                'desi'             => $product->desi !== null ? (float) $product->desi : null,
                'shippingTime'     => $product->shipping_time,
                'shippingFee'      => $product->shipping_fee !== null ? (float) $product->shipping_fee : null,
                'freeShipping'     => $product->free_shipping,
                'minOrderQty'      => $product->min_order_qty,
                'orderMultiple'    => $product->order_multiple,
                'material'         => $product->material,
                'careInstructions' => $product->care_instructions,
                'images'           => $images,
            ],
            'warehouses' => $warehouses,
            'variants'   => $variantRows,
            'movements'  => $movements,
            'totals'     => $totals,
            'marketplaces' => $marketplaces,
            'listings'     => $listings,
            // Üretilecek ürünün iş emri Atölye'de verilir (Product → Atelier bağımlılığı yasak);
            // yalnız route adı + yetkiyle bağlanır, kod bağımlılığı kurulmaz.
            'productionOrderUrl' => $product->sourcing === Product::SOURCING_MANUFACTURED
                && Route::has('atelier.production-orders.index')
                && $request->user()?->can('atelier.production.manage')
                    ? route('atelier.production-orders.index', ['product_id' => $product->id], false)
                    : null,
        ]);
    }
}
