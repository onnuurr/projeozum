<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderMaterial;

/**
 * İş emri hammadde rezervi: planlamada ayrılır (stok düşmez), tüketen aşamada (kesim)
 * parti kaydedildikçe rezervden gerçek stok çıkışı yapılır, kapatma/iptalde kalan serbest kalır.
 * Kullanılabilir = materials.current_stock − Σ açık rezerv.
 */
class MaterialReservationService
{
    public function __construct(private MaterialStockService $materialStock) {}

    /**
     * Malzeme id → açık rezerv miktarı. $exceptOrderId verilirse o emrin rezervi sayılmaz.
     *
     * @param  array<int, int>|null  $materialIds
     * @return array<int, float>
     */
    public function reservedByMaterial(?array $materialIds = null, ?int $exceptOrderId = null): array
    {
        return ProductionOrderMaterial::query()
            ->whereNull('released_at')
            ->when($materialIds !== null, fn ($q) => $q->whereIn('material_id', $materialIds))
            ->when($exceptOrderId, fn ($q) => $q->where('production_order_id', '!=', $exceptOrderId))
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(required_qty - consumed_qty) as open_qty')
            ->pluck('open_qty', 'material_id')
            ->map(fn ($v) => max(0.0, round((float) $v, 3)))
            ->all();
    }

    public function available(Material $material): float
    {
        $reserved = $this->reservedByMaterial([$material->id])[$material->id] ?? 0.0;

        return round((float) $material->current_stock - $reserved, 3);
    }

    /**
     * Reçete gereksinimini rezerve eder; kullanılabilir miktar yetmezse hiçbir şey yazmadan hata.
     *
     * @param  array<int, array{material_id:int, material_name:string, per_unit_qty:float,
     *   required_qty:float, unit_cost:float}>  $requirements  BomService::requirementsFor()
     */
    public function reserve(ProductionOrder $order, array $requirements): void
    {
        DB::transaction(function () use ($order, $requirements) {
            $ids = array_column($requirements, 'material_id');
            $materials = Material::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $reserved = $this->reservedByMaterial($ids, $order->id);

            foreach ($requirements as $req) {
                $material = $materials->get($req['material_id']);
                $available = $material
                    ? round((float) $material->current_stock - ($reserved[$material->id] ?? 0), 3)
                    : 0.0;

                if ($available < $req['required_qty']) {
                    throw new InvalidArgumentException(
                        "Yetersiz hammadde: {$req['material_name']} (gerekli {$req['required_qty']}, kullanılabilir {$available})"
                    );
                }
            }

            foreach ($requirements as $req) {
                ProductionOrderMaterial::updateOrCreate(
                    ['production_order_id' => $order->id, 'material_id' => $req['material_id']],
                    [
                        'per_unit_qty' => $req['per_unit_qty'],
                        'required_qty' => $req['required_qty'],
                        'consumed_qty' => 0,
                        'unit_cost'    => $req['unit_cost'],
                        'released_at'  => null,
                    ],
                );
            }
        });
    }

    /**
     * $units adet kesildi: her malzemeden adet başı × $units kadar (açık rezervle sınırlı) stok çıkışı.
     */
    public function consume(ProductionOrder $order, int $units): void
    {
        if ($units <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $units) {
            $rows = ProductionOrderMaterial::query()
                ->with('material')
                ->where('production_order_id', $order->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $qty = round(min((float) $row->per_unit_qty * $units, $row->openQty()), 3);
                if ($qty <= 0 || ! $row->material) {
                    continue;
                }

                $this->materialStock->record(
                    $row->material,
                    MaterialMovement::TYPE_OUT,
                    $qty,
                    'consume',
                    [
                        'production_order_id' => $order->id,
                        'unit_cost'           => $row->unit_cost,
                        'note'                => "Üretim emri {$order->code} tüketimi ({$units} adet)",
                    ],
                );

                $row->update(['consumed_qty' => round((float) $row->consumed_qty + $qty, 3)]);
            }
        });
    }

    /** Kalan (tüketilmemiş) rezervi serbest bırakır; tüketilen stok geri dönmez. */
    public function release(ProductionOrder $order): void
    {
        ProductionOrderMaterial::query()
            ->where('production_order_id', $order->id)
            ->whereNull('released_at')
            ->update(['released_at' => now()]);
    }

    /** Gerçek malzeme maliyeti: tüketilen × planlama anı birim maliyeti. */
    public function consumedCost(ProductionOrder $order): float
    {
        return round((float) ProductionOrderMaterial::query()
            ->where('production_order_id', $order->id)
            ->get()
            ->sum(fn (ProductionOrderMaterial $m) => (float) $m->consumed_qty * (float) $m->unit_cost), 2);
    }
}
