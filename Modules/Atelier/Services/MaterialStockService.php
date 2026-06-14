<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;

class MaterialStockService
{
    /**
     * Hammadde hareketi yazar ve current_stock'u günceller.
     *
     * @param  string  $type    in|out|adjust
     * @param  float   $quantity Pozitif girilir; out için içeride negatife çevrilir.
     * @param  string  $reason  purchase|consume|scrap|correction
     */
    public function record(
        Material $material,
        string $type,
        float $quantity,
        string $reason,
        array $opts = []
    ): MaterialMovement {
        return DB::transaction(function () use ($material, $type, $quantity, $reason, $opts) {
            $locked = Material::query()->lockForUpdate()->findOrFail($material->id);
            $before = (float) $locked->current_stock;

            $signed = match ($type) {
                MaterialMovement::TYPE_IN     => abs($quantity),
                MaterialMovement::TYPE_OUT    => -abs($quantity),
                MaterialMovement::TYPE_ADJUST => (float) $quantity,
                default => throw new InvalidArgumentException("Geçersiz hareket tipi: {$type}"),
            };

            $after = $before + $signed;
            if ($after < 0) {
                throw new InvalidArgumentException('Hammadde stoğu negatife düşemez.');
            }

            $locked->update(['current_stock' => $after]);

            $movement = MaterialMovement::create([
                'material_id'         => $locked->id,
                'type'                => $type,
                'quantity'            => $signed,
                'unit_cost'           => $opts['unit_cost'] ?? $locked->unit_cost,
                'reason'              => $reason,
                'before_stock'        => $before,
                'after_stock'         => $after,
                'production_order_id' => $opts['production_order_id'] ?? null,
                'note'                => $opts['note'] ?? null,
                'created_by'          => $opts['created_by'] ?? auth()->id(),
            ]);

            $material->setRawAttributes($locked->getAttributes());

            return $movement;
        });
    }
}
