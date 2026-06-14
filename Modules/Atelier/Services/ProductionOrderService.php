<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Models\ProductionOrder;

class ProductionOrderService
{
    public function __construct(
        private BomService $bom,
        private MaterialStockService $materialStock,
        private FinishedGoodsService $finishedGoods,
    ) {}

    /**
     * draft -> planned: BOM gereksinimini hesaplar, hammadde stoğunu düşer, malzeme maliyetini yazar.
     */
    public function plan(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== ProductionOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Yalnızca taslak iş emri planlanabilir.');
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('product');
            $requirements = $this->bom->requirementsFor($order->product, (float) $order->planned_qty);

            // Önce stok yeterliliğini kontrol et (tek tek düşmeden).
            foreach ($requirements as $req) {
                $material = Material::lockForUpdate()->find($req['material_id']);
                if (! $material || (float) $material->current_stock < $req['required_qty']) {
                    throw new InvalidArgumentException(
                        "Yetersiz hammadde: {$req['material_name']} (gerekli {$req['required_qty']}, mevcut " .
                        ($material ? $material->current_stock : 0) . ')'
                    );
                }
            }

            $materialCost = 0.0;
            foreach ($requirements as $req) {
                $material = Material::find($req['material_id']);
                $this->materialStock->record(
                    $material,
                    MaterialMovement::TYPE_OUT,
                    $req['required_qty'],
                    'consume',
                    ['production_order_id' => $order->id, 'note' => "Üretim emri {$order->code} tüketimi"],
                );
                $materialCost += $req['line_cost'];
            }

            $order->update([
                'status'        => ProductionOrder::STATUS_PLANNED,
                'material_cost' => round($materialCost, 2),
            ]);

            return $order;
        });
    }

    /**
     * -> completed: adım maliyetlerini toplar, biten ürünü stoğa alır, birim maliyeti hesaplar.
     */
    public function complete(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Bu durumdaki iş emri tamamlanamaz.');
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('steps', 'items');

            $fasonCost = (float) $order->steps->where('location_type', 'fason')->sum('step_cost');
            $laborCost = (float) $order->steps->where('location_type', 'in_house')->sum('step_cost');
            $producedQty = (int) $order->items->sum('produced_qty');

            $totalCost = (float) $order->material_cost + $fasonCost + $laborCost;
            $unitCost = $producedQty > 0 ? round($totalCost / $producedQty, 2) : 0;

            $order->update([
                'fason_cost'   => $fasonCost,
                'labor_cost'   => $laborCost,
                'produced_qty' => $producedQty,
            ]);

            $this->finishedGoods->receiveIntoStock($order);

            $order->update([
                'total_cost' => $totalCost,
                'unit_cost'  => $unitCost,
                'status'     => ProductionOrder::STATUS_COMPLETED,
            ]);

            return $order;
        });
    }
}
