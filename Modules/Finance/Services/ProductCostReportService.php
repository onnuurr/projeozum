<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Product\Models\Product;

/**
 * Ürün maliyeti raporu: kendi ürettiğimiz ürünler için Atelier ProductionOrder
 * kayıtlarından (material_cost/fason_cost/labor_cost/unit_cost) gerçek üretim
 * maliyetini okur; tamamlanmış bir üretim emri yoksa Product.purchase_price'a
 * düşer. Hiçbir veri kopyalanmaz — sadece ilgili tablolar okunup birleştirilir.
 */
class ProductCostReportService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(): Collection
    {
        $productionByProduct = ProductionOrder::query()
            ->where('status', ProductionOrder::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        return Product::query()
            ->select(['id', 'name', 'sku', 'price', 'purchase_price'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($productionByProduct) {
                $orders = $productionByProduct->get($product->id);

                if ($orders && $orders->isNotEmpty()) {
                    $latest       = $orders->first(); // orderByDesc('id') → en son tamamlanan
                    $unitCost     = (float) $latest->unit_cost;
                    $avgUnitCost  = round((float) $orders->avg('unit_cost'), 2);
                    $producedQty  = (int) $orders->sum('produced_qty');
                    $source       = 'production';
                } else {
                    $unitCost    = (float) $product->purchase_price;
                    $avgUnitCost = $unitCost;
                    $producedQty = 0;
                    $source      = 'purchase_price';
                }

                $price  = (float) $product->price;
                $margin = $price - $unitCost;

                return [
                    'id'              => $product->id,
                    'name'            => $product->name,
                    'sku'             => $product->sku,
                    'source'          => $source,
                    'unitCost'        => $unitCost,
                    'avgUnitCost'     => $avgUnitCost,
                    'producedQty'     => $producedQty,
                    'productionRuns'  => $orders?->count() ?? 0,
                    'price'           => $price,
                    'margin'          => round($margin, 2),
                    'marginRate'      => $price > 0 ? round(($margin / $price) * 100, 1) : null,
                ];
            });
    }
}
