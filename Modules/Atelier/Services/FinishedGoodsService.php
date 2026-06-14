<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderItem;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;

class FinishedGoodsService
{
    /**
     * İş emrinin biten ürünlerini (varyant bazında produced_qty) hedef depoya stok girişi yapar.
     * StockController@movement deseniyle birebir: Stock upsert + StockMovement + variant denormalize.
     */
    public function receiveIntoStock(ProductionOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                $qty = (int) $item->produced_qty;
                if ($qty <= 0) {
                    continue;
                }

                $this->addStock($order, $item, $qty);
            }
        });
    }

    private function addStock(ProductionOrder $order, ProductionOrderItem $item, int $qty): void
    {
        $stock = Stock::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->where('warehouse_id', $order->warehouse_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = Stock::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id'       => $order->warehouse_id,
                'quantity'           => 0,
            ]);
        }

        $before = (int) $stock->quantity;
        $after = $before + $qty;
        $stock->update(['quantity' => $after]);

        StockMovement::create([
            'product_variant_id' => $item->product_variant_id,
            'warehouse_id'       => $order->warehouse_id,
            'type'               => StockMovement::TYPE_IN,
            'quantity'           => $qty,
            'before_quantity'    => $before,
            'after_quantity'     => $after,
            'reference_type'     => ProductionOrder::class,
            'reference_id'       => $order->id,
            'note'               => "Üretim emri {$order->code} girişi",
            'user_id'            => auth()->id(),
        ]);

        $variant = ProductVariant::find($item->product_variant_id);
        $variant?->update([
            'stock' => (int) Stock::where('product_variant_id', $variant->id)->sum('quantity'),
        ]);
    }
}
