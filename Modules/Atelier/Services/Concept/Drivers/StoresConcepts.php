<?php

namespace Modules\Atelier\Services\Concept\Drivers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konsept sürücüleri için ortak kalıcı saklama yardımcısı.
 * Ham PNG baytlarını config'teki disk/dizine yazar, göreli yolu döndürür.
 */
trait StoresConcepts
{
    protected function store(string $bytes): string
    {
        $disk = (string) config('atelier.concept.disk', 'public');
        $dir  = trim((string) config('atelier.concept.dir', 'atelier/concepts'), '/');
        $path = $dir . '/' . Str::uuid() . '.png';

        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }
}
