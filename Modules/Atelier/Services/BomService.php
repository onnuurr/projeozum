<?php

namespace Modules\Atelier\Services;

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
}
