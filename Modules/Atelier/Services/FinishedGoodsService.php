<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Product\Models\StockMovement;
use Modules\Product\Services\StockService;

class FinishedGoodsService
{
    public function __construct(private StockService $stock) {}

    /**
     * Stoğa alınan bitmiş ürünü (varyant id → adet) iş emrinin deposuna giriş yapar.
     * Tek stok mutasyon noktası Product StockService — StockChanged (Bagisto stok push) oradan ateşlenir.
     *
     * @param  array<int, int>  $quantities
     */
    public function receiveIntoStock(ProductionOrder $order, array $quantities): void
    {
        DB::transaction(function () use ($order, $quantities) {
            foreach ($quantities as $variantId => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                $this->stock->move(
                    (int) $variantId,
                    $order->warehouse_id,
                    StockMovement::TYPE_IN,
                    (int) $qty,
                    $order,
                    "Üretim emri {$order->code} girişi",
                    auth()->id(),
                );
            }
        });
    }
}
