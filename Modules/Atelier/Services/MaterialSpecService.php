<?php

namespace Modules\Atelier\Services;

use Illuminate\Validation\ValidationException;
use Modules\Atelier\Models\Material;

/**
 * Material.specs (JSONB) yönetimi. Free-form değil — allowlist'li key seti;
 * bilinmeyen key gelirse ValidationException. AI prompt için okunabilir metne
 * çevirme sorumluluğu da burada.
 */
class MaterialSpecService
{
    public const ALLOWED_KEYS = [
        'composition',
        'gsm',
        'width_cm',
        'weave',
        'finish',
        'care_instructions',
        'fiber_origin',
        'notes',
    ];

    public function updateSpecs(Material $material, array $specs): Material
    {
        $unknown = array_diff(array_keys($specs), self::ALLOWED_KEYS);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'specs' => 'Bilinmeyen alan(lar): ' . implode(', ', $unknown),
            ]);
        }

        $clean = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $specs)) {
                continue;
            }
            $value = $specs[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = is_string($value) ? trim($value) : $value;
        }

        $material->specs = $clean === [] ? null : $clean;
        $material->save();

        return $material->fresh();
    }

    public function formatForPrompt(Material $material): string
    {
        $specs = (array) ($material->specs ?? []);
        $parts = [];

        if (! empty($specs['composition'])) {
            $parts[] = 'kompozisyon: ' . $specs['composition'];
        }
        if (! empty($specs['gsm'])) {
            $parts[] = $specs['gsm'] . ' gsm';
        }
        if (! empty($specs['width_cm'])) {
            $parts[] = 'genişlik ' . $specs['width_cm'] . ' cm';
        }
        if (! empty($specs['weave'])) {
            $parts[] = $specs['weave'] . ' örgü/dokuma';
        }
        if (! empty($specs['finish'])) {
            $parts[] = $specs['finish'] . ' terbiye';
        }
        if (! empty($specs['fiber_origin'])) {
            $parts[] = 'lif menşei: ' . $specs['fiber_origin'];
        }
        if (! empty($specs['care_instructions'])) {
            $parts[] = 'bakım: ' . $specs['care_instructions'];
        }
        if (! empty($specs['notes'])) {
            $parts[] = 'not: ' . $specs['notes'];
        }

        $base = trim(($material->name ?? '') . ' (' . ($material->code ?? '') . ')');
        if ($parts === []) {
            return $base;
        }

        return $base . ' — ' . implode(', ', $parts);
    }
}
