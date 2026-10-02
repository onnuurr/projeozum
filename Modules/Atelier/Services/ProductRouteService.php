<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductRouteStep;

/**
 * Ürün rotası: yeni iş emrinin aşamaları buradan otomatik dolar. Ürünün rotası yoksa
 * operasyonlar tanımlı sırayla (sort_order) ve varsayılan yer/fiyatla önerilir.
 */
class ProductRouteService
{
    public const SOURCE_PRODUCT   = 'product';
    public const SOURCE_SUGGESTED = 'suggested';

    /**
     * @return array{source:string, steps:list<array{operation_id:int, sequence:int, location_type:string,
     *   fason_supplier_id:int|null, unit_cost:float}>}
     */
    public function forProduct(int $productId): array
    {
        $steps = ProductRouteStep::query()->where('product_id', $productId)->orderBy('sequence')->get();

        if ($steps->isNotEmpty()) {
            return [
                'source' => self::SOURCE_PRODUCT,
                'steps'  => $steps->map(fn (ProductRouteStep $s) => [
                    'operation_id'      => $s->operation_id,
                    'sequence'          => $s->sequence,
                    'location_type'     => $s->location_type,
                    'fason_supplier_id' => $s->fason_supplier_id,
                    'unit_cost'         => (float) $s->unit_cost,
                ])->all(),
            ];
        }

        return [
            'source' => self::SOURCE_SUGGESTED,
            'steps'  => Operation::query()->orderBy('sort_order')->orderBy('id')->get()
                ->values()
                ->map(fn (Operation $o, int $i) => [
                    'operation_id'      => $o->id,
                    'sequence'          => $i + 1,
                    'location_type'     => $o->default_location,
                    'fason_supplier_id' => null,
                    'unit_cost'         => (float) $o->default_unit_cost,
                ])->all(),
        ];
    }

    /**
     * Ürünün rotasını verilen adımlarla değiştirir; sıra, gelen sequence'e göre 1..n normalize edilir.
     *
     * @param  array<int, array{operation_id:int, sequence:int, location_type:string,
     *   fason_supplier_id?:int|null, unit_cost?:float|null}>  $steps
     */
    public function replace(int $productId, array $steps): void
    {
        foreach ($steps as $step) {
            if ($step['location_type'] === 'fason' && empty($step['fason_supplier_id'])) {
                throw new InvalidArgumentException('Fason aşama için fasoncu seçilmeli.');
            }
        }

        DB::transaction(function () use ($productId, $steps) {
            ProductRouteStep::where('product_id', $productId)->delete();

            foreach (collect($steps)->sortBy('sequence')->values() as $i => $step) {
                ProductRouteStep::create([
                    'product_id'        => $productId,
                    'operation_id'      => $step['operation_id'],
                    'sequence'          => $i + 1,
                    'location_type'     => $step['location_type'],
                    'fason_supplier_id' => $step['location_type'] === 'fason' ? $step['fason_supplier_id'] : null,
                    'unit_cost'         => $step['unit_cost'] ?? 0,
                ]);
            }
        });
    }
}
