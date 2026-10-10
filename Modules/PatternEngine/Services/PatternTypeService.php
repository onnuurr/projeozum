<?php

namespace Modules\PatternEngine\Services;

use Illuminate\Support\Facades\DB;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Models\PatternMeasurementPoint;
use Modules\PatternEngine\Models\PatternType;

/**
 * Kalıp Ayarları: kalıp tipleri (+ ölçü şablonu) ve ölçü noktası sözlüğü.
 */
class PatternTypeService
{
    /**
     * @param  array{name:string,is_active?:bool,sort_order?:int}  $data
     * @param  array<int,int>  $pointIds  şablon sırası = dizi sırası
     */
    public function saveType(?PatternType $type, array $data, array $pointIds): PatternType
    {
        return DB::transaction(function () use ($type, $data, $pointIds) {
            $oldName = $type?->name;
            $type ??= new PatternType();
            $type->fill($data)->save();

            $sync = [];
            foreach (array_values(array_unique($pointIds)) as $i => $id) {
                $sync[$id] = ['sort_order' => ($i + 1) * 10];
            }
            $type->measurementPoints()->sync($sync);

            // product_type metni tip adıyla senkron (filtreler + AI rule engine onu okur).
            if ($oldName !== null && $oldName !== $type->name) {
                Pattern::query()->where('pattern_type_id', $type->id)->update(['product_type' => $type->name]);
            }

            return $type;
        });
    }

    /** Kullanımdaki tip silinmez (kalıplar bağlı) — pasife alınır. */
    public function deleteType(PatternType $type): bool
    {
        if ($type->patterns()->exists()) {
            $type->update(['is_active' => false]);

            return false;
        }
        $type->delete();

        return true;
    }

    /** @param  array<string,mixed>  $data */
    public function savePoint(?PatternMeasurementPoint $point, array $data): PatternMeasurementPoint
    {
        $point ??= new PatternMeasurementPoint();
        $point->fill($data)->save();

        return $point;
    }

    /** Değeri girilmiş nokta silinmez (veri kaybı) — pasife alınır. */
    public function deletePoint(PatternMeasurementPoint $point): bool
    {
        if (DB::table('pattern_measurements')->where('measurement_point_id', $point->id)->exists()) {
            $point->update(['is_active' => false]);

            return false;
        }
        $point->delete();

        return true;
    }
}
