<?php

namespace Modules\PatternEngine\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Models\PatternMeasurement;
use Modules\PatternEngine\Models\PatternMeasurementPoint;

/**
 * Kalıp Kartı ölçü tabloları: beden × ölçü noktası değerleri (vücut + hazır giysi).
 *
 * Satırlar kalıp tipinin şablonundan (PatternType::measurementPoints) gelir; şablonda
 * olmayan ölçüler custom_label'lı serbest satır olarak tutulur. PDF'ten okunan
 * değerler source=pdf işaretlenir; yeniden çıkarım YALNIZCA bunları yeniler —
 * operatörün elle girdiği/düzelttiği hücreler (source=manual) asla ezilmez.
 */
class PatternMeasurementService
{
    /** Nipnaps Maßtabelle kısaltmaları → sözlük anahtarı (eski {labels,sizes,matrix} biçimi). */
    private const LEGACY_LABELS = [
        'KH' => 'height',      // Körperhöhe
        'OW' => 'chest_circ',  // Oberweite
        'TW' => 'waist_circ',  // Taillenweite
        'HW' => 'hip_circ',    // Hüftweite
        'SL' => 'side_length', // Seitenlänge
    ];

    /**
     * Kart için tablolar: basis+grup başına satırlar (şablon satırları boş olsa da gelir).
     *
     * @return array<int,array{basis:string,group_label:?string,rows:array<int,array<string,mixed>>}>
     */
    public function tables(Pattern $pattern): array
    {
        $template = $pattern->type?->measurementPoints()->where('is_active', true)->get() ?? collect();
        $values = $pattern->measurements()->with('point')->get();

        $tables = [];
        foreach (PatternMeasurementPoint::BASES as $basis) {
            $groups = $values->where('basis', $basis)->pluck('group_label')->unique()->values();
            if ($groups->isEmpty()) {
                $groups = collect([null]);
            }
            foreach ($groups as $group) {
                $tables[] = [
                    'basis'       => $basis,
                    'group_label' => $group,
                    'rows'        => $this->rows($template->where('basis', $basis), $values->where('basis', $basis)->where('group_label', $group)),
                ];
            }
        }

        return $tables;
    }

    /**
     * @param  Collection<int,PatternMeasurementPoint>  $template
     * @param  Collection<int,PatternMeasurement>       $values
     * @return array<int,array<string,mixed>>
     */
    private function rows(Collection $template, Collection $values): array
    {
        $rows = [];
        foreach ($template as $point) {
            $rows['p' . $point->id] = ['point_id' => $point->id, 'custom_label' => null, 'label' => $point->name, 'hint' => $point->hint, 'values' => [], 'sources' => []];
        }
        foreach ($values as $v) {
            $key = $v->measurement_point_id ? 'p' . $v->measurement_point_id : 'c' . $v->custom_label;
            $rows[$key] ??= [
                'point_id'     => $v->measurement_point_id,
                'custom_label' => $v->custom_label,
                'label'        => $v->point?->name ?? $v->custom_label,
                'hint'         => $v->point?->hint,
                'values'       => [],
                'sources'      => [],
            ];
            $rows[$key]['values'][$v->size_label] = $v->value;
            $rows[$key]['sources'][$v->size_label] = $v->source;
        }

        return array_values($rows);
    }

    /**
     * Operatör kaydı: kalıbın TÜM ölçü tablolarını gönderilenle değiştirir. Değeri
     * değişmeyen PDF hücreleri source=pdf kalır; değişen/yeni hücreler manual olur.
     *
     * @param  array<int,array{basis:string,group_label?:?string,rows:array<int,array{point_id?:?int,custom_label?:?string,values:array<string,mixed>}>}>  $tables
     */
    public function save(Pattern $pattern, array $tables): void
    {
        DB::transaction(function () use ($pattern, $tables) {
            $previous = [];
            foreach ($pattern->measurements()->get() as $m) {
                $previous[$this->cellKey($m->basis, $m->group_label, $m->measurement_point_id, $m->custom_label, $m->size_label)] = $m;
            }

            $pattern->measurements()->delete();

            foreach ($tables as $table) {
                $basis = $table['basis'];
                $group = $this->blankToNull($table['group_label'] ?? null);
                foreach ($table['rows'] ?? [] as $row) {
                    $pointId = isset($row['point_id']) ? (int) $row['point_id'] : null;
                    $custom = $pointId ? null : $this->blankToNull($row['custom_label'] ?? null);
                    if (! $pointId && $custom === null) {
                        continue;
                    }
                    foreach ($row['values'] ?? [] as $size => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $value = (float) str_replace(',', '.', (string) $value);
                        $old = $previous[$this->cellKey($basis, $group, $pointId, $custom, (string) $size)] ?? null;
                        $source = $old && $old->source === PatternMeasurement::SOURCE_PDF && abs($old->value - $value) < 0.005
                            ? PatternMeasurement::SOURCE_PDF
                            : PatternMeasurement::SOURCE_MANUAL;

                        $pattern->measurements()->create([
                            'measurement_point_id' => $pointId,
                            'custom_label'         => $custom,
                            'basis'                => $basis,
                            'group_label'          => $group,
                            'size_label'           => (string) $size,
                            'value'                => $value,
                            'source'               => $source,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Dönüştürücü çıktısındaki ölçüleri source=pdf olarak yazar. Önceki PDF
     * hücreleri silinir; elle girilmiş hücrenin olduğu yere PDF değeri YAZILMAZ.
     *
     * Kabul edilen biçimler:
     *  - body_measurements: [{group, point_key, label?, values:{beden:değer}}]  (Burda vb.)
     *  - measurements (eski): {labels, sizes, matrix:{etiket:[değerler]}}      (Nipnaps)
     *
     * @param  array<string,mixed>  $meta
     */
    public function importFromPdf(Pattern $pattern, array $meta): void
    {
        $rows = $meta['body_measurements'] ?? [];
        foreach ($this->legacyRows($meta['measurements'] ?? null) as $row) {
            $rows[] = $row;
        }
        if ($rows === []) {
            return;
        }

        $pointIds = PatternMeasurementPoint::query()->pluck('id', 'key');

        DB::transaction(function () use ($pattern, $rows, $pointIds) {
            $pattern->measurements()->where('source', PatternMeasurement::SOURCE_PDF)->delete();
            $manual = [];
            foreach ($pattern->measurements()->get() as $m) {
                $manual[$this->cellKey($m->basis, $m->group_label, $m->measurement_point_id, $m->custom_label, $m->size_label)] = true;
            }

            foreach ($rows as $row) {
                $basis = $row['basis'] ?? PatternMeasurementPoint::BASIS_BODY;
                $group = $this->blankToNull($row['group'] ?? null);
                $pointId = isset($row['point_key']) ? ($pointIds[$row['point_key']] ?? null) : null;
                $custom = $pointId ? null : $this->blankToNull($row['label'] ?? $row['point_key'] ?? null);
                if (! $pointId && $custom === null) {
                    continue;
                }
                foreach ($row['values'] ?? [] as $size => $value) {
                    if ($value === null || ! is_numeric($value)) {
                        continue;
                    }
                    if (isset($manual[$this->cellKey($basis, $group, $pointId, $custom, (string) $size)])) {
                        continue;
                    }
                    $pattern->measurements()->create([
                        'measurement_point_id' => $pointId,
                        'custom_label'         => $custom,
                        'basis'                => $basis,
                        'group_label'          => $group,
                        'size_label'           => (string) $size,
                        'value'                => (float) $value,
                        'source'               => PatternMeasurement::SOURCE_PDF,
                    ]);
                }
            }
        });
    }

    /** @return array<int,array<string,mixed>> */
    private function legacyRows(mixed $legacy): array
    {
        if (! is_array($legacy) || empty($legacy['matrix']) || empty($legacy['sizes'])) {
            return [];
        }
        $rows = [];
        foreach ($legacy['matrix'] as $label => $vals) {
            $values = [];
            foreach (array_values($legacy['sizes']) as $i => $size) {
                $values[(string) $size] = $vals[$i] ?? null;
            }
            $rows[] = [
                'basis'     => PatternMeasurementPoint::BASIS_BODY,
                'point_key' => self::LEGACY_LABELS[$label] ?? null,
                'label'     => $label,
                'values'    => $values,
            ];
        }

        return $rows;
    }

    private function cellKey(string $basis, ?string $group, ?int $pointId, ?string $custom, string $size): string
    {
        return implode('|', [$basis, $group ?? '', $pointId ? 'p' . $pointId : 'c' . $custom, $size]);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
