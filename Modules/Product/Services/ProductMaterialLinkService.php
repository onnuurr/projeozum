<?php

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\Material;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductDescriptionMaterial;

/**
 * Ürünün açıklama üretimi için hangi materyallerin (kumaş vs.) kullanıldığını yönetir.
 *
 * İki kaynak var:
 *  - product_description_materials pivot (admin doğrudan seçmişse)
 *  - product_boms → bom_lines (üretim reçetesi varsa)
 *
 * resolve() önce pivot'a bakar; yoksa reçetenin ilk aktif kaydından çeker.
 */
class ProductMaterialLinkService
{
    /**
     * @param  array<int, array{material_id: int, role: string, sort_order?: int, notes?: string|null}>  $rows
     */
    public function syncMaterials(Product $product, array $rows): void
    {
        DB::transaction(function () use ($product, $rows) {
            $keepIds = [];

            foreach ($rows as $idx => $row) {
                if (empty($row['material_id']) || empty($row['role'])) {
                    continue;
                }

                $record = ProductDescriptionMaterial::updateOrCreate(
                    [
                        'product_id'  => $product->id,
                        'material_id' => (int) $row['material_id'],
                        'role'        => $row['role'],
                    ],
                    [
                        'sort_order' => $row['sort_order'] ?? $idx,
                        'notes'      => $row['notes'] ?? null,
                    ],
                );

                $keepIds[] = $record->id;
            }

            $stale = ProductDescriptionMaterial::query()->where('product_id', $product->id);
            if ($keepIds !== []) {
                $stale->whereNotIn('id', $keepIds);
            }
            $stale->delete();
        });
    }

    /**
     * Ürün için materyal listesi + rol bilgisi döndürür.
     *
     * Return shape:
     *   Collection<int, array{material: Material, role: string, sort_order: int, notes: ?string}>
     */
    public function resolve(Product $product): Collection
    {
        $pivotRows = ProductDescriptionMaterial::query()
            ->with('material')
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductDescriptionMaterial $r) => [
                'material'   => $r->material,
                'role'       => $r->role,
                'sort_order' => (int) $r->sort_order,
                'notes'      => $r->notes,
            ]);

        if ($pivotRows->isNotEmpty()) {
            return $pivotRows->filter(fn ($row) => $row['material'] !== null)->values();
        }

        $bom = $product->boms()->where('is_active', true)->with('lines.material')->first()
            ?? $product->boms()->with('lines.material')->first();

        if (! $bom) {
            return collect();
        }

        return collect($bom->lines)
            ->filter(fn ($line) => $line->material !== null)
            ->values()
            ->map(fn ($line, $idx) => [
                'material'   => $line->material,
                'role'       => ProductDescriptionMaterial::ROLE_PRIMARY_FABRIC,
                'sort_order' => $idx,
                'notes'      => null,
            ]);
    }
}
