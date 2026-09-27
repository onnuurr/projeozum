<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Product\Models\Product;

class BomService
{
    /**
     * Verilen ürün ve adet için gereken malzeme listesini hesaplar.
     *
     * @return array<int, array{material_id:int, material_name:string, unit:string,
     *   required_qty:float, unit_cost:float, line_cost:float}>
     */
    public function requirementsFor(Product $product, float $quantity): array
    {
        $bom = ProductBom::query()
            ->with('lines.material')
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $bom) {
            return [];
        }

        return $bom->lines->map(function ($line) use ($quantity) {
            $perUnit = (float) $line->quantity_per_unit * (1 + (float) $line->waste_pct / 100);
            $required = $perUnit * $quantity;
            $unitCost = (float) ($line->material->unit_cost ?? 0);

            return [
                'material_id'   => $line->material_id,
                'material_name' => $line->material->name ?? '',
                'unit'          => $line->material->unit ?? '',
                'required_qty'  => round($required, 3),
                'unit_cost'     => $unitCost,
                'line_cost'     => round($required * $unitCost, 2),
            ];
        })->all();
    }

    /**
     * Ürünün reçetesini yazar: ürün başına tek aktif reçete kuralı gereği öncekiler pasiflenir.
     *
     * @param  array<int, array{material_id:int, quantity_per_unit:float, waste_pct?:float|null}>  $lines
     */
    public function replaceActive(int $productId, string $name, array $lines, float $extraCostPerUnit = 0): ProductBom
    {
        return DB::transaction(function () use ($productId, $name, $lines, $extraCostPerUnit) {
            ProductBom::where('product_id', $productId)->update(['is_active' => false]);

            $bom = ProductBom::create([
                'product_id'          => $productId,
                'name'                => $name,
                'extra_cost_per_unit' => $extraCostPerUnit,
                'is_active'           => true,
            ]);

            foreach ($lines as $line) {
                $bom->lines()->create([
                    'material_id'       => $line['material_id'],
                    'quantity_per_unit' => $line['quantity_per_unit'],
                    'waste_pct'         => $line['waste_pct'] ?? 0,
                ]);
            }

            return $bom;
        });
    }

    /**
     * Kaydedilmemiş reçete satırlarından 1 adetlik ön maliyet (requirementsFor ile aynı formül,
     * malzeme kartındaki güncel birim maliyetle) + tahmini işçilik/fason ek maliyeti.
     *
     * @param  array<int, array{material_id:int, quantity_per_unit:float, waste_pct?:float|null}>  $lines
     * @return array{material_cost:float, extra_cost:float, unit_cost:float}
     */
    public function unitCostFor(array $lines, float $extraCostPerUnit = 0): array
    {
        $costs = Material::query()
            ->whereIn('id', array_column($lines, 'material_id'))
            ->pluck('unit_cost', 'id');

        $materialCost = 0.0;
        foreach ($lines as $line) {
            $perUnit = (float) $line['quantity_per_unit'] * (1 + (float) ($line['waste_pct'] ?? 0) / 100);
            $materialCost += $perUnit * (float) ($costs[$line['material_id']] ?? 0);
        }

        return [
            'material_cost' => round($materialCost, 2),
            'extra_cost'    => round($extraCostPerUnit, 2),
            'unit_cost'     => round($materialCost + $extraCostPerUnit, 2),
        ];
    }
}
