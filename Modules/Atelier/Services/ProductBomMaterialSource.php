<?php

namespace Modules\Atelier\Services;

use App\Contracts\ProductBomMaterialSource as ProductBomMaterialSourceContract;
use Illuminate\Support\Collection;
use Modules\Atelier\Models\ProductBom;

/**
 * Product'ın reçete materyali ihtiyacını (App\Contracts\ProductBomMaterialSource) Atelier
 * tarafında karşılar — Product, Atelier modellerini doğrudan sorgulamaz.
 */
class ProductBomMaterialSource implements ProductBomMaterialSourceContract
{
    public function materialsFor(int $productId): Collection
    {
        $bom = ProductBom::query()
            ->where('product_id', $productId)
            ->with('lines.material')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        if (! $bom) {
            return collect();
        }

        return $bom->lines
            ->pluck('material')
            ->filter()
            ->values();
    }
}
