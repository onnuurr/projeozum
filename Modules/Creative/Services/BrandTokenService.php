<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\BrandKit;

/**
 * Marka token'ları için tek doğruluk kaynağı.
 *
 * Varsayılan brand kit'i okur, normalize eder ve cache'ler. Render, AI sahne ve
 * caption katmanları markaya dair renk/font/boşluk/logo bilgisini yalnız buradan alır.
 * Çıktı sözleşmesi (normalize edilmiş):
 *   [
 *     'palette'  => ['primary' => '#rrggbb', ...],
 *     'fonts'    => ['regular' => '/abs/path.ttf', 'bold' => '/abs/path.ttf'],
 *     'spacing'  => ['md' => 16, ...],
 *     'logos'    => ['primary' => '/abs/path.png', ...],
 *   ]
 */
class BrandTokenService
{
    private const CACHE_KEY = 'creative.brand_tokens.default';
    private const CACHE_TTL = 3600;

    /**
     * Varsayılan kit'in normalize token sözlüğü (cache'li).
     *
     * @return array{palette:array<string,string>,fonts:array<string,string>,spacing:array<string,mixed>,logos:array<string,string>}
     */
    public function tokens(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->normalize(BrandKit::resolveDefault());
        });
    }

    /**
     * Belirli bir kit için token sözlüğü (cache'siz; önizleme/editör için).
     *
     * @return array{palette:array<string,string>,fonts:array<string,string>,spacing:array<string,mixed>,logos:array<string,string>}
     */
    public function tokensFor(?BrandKit $kit): array
    {
        return $this->normalize($kit);
    }

    /**
     * Varsayılan token cache'ini temizler (kit kaydedilince/silinince çağrılır).
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{palette:array<string,string>,fonts:array<string,string>,spacing:array<string,mixed>,logos:array<string,string>}
     */
    private function normalize(?BrandKit $kit): array
    {
        $defaults = (array) config('creative.brand.defaults', []);

        $palette = array_merge(
            (array) ($defaults['palette'] ?? []),
            $this->stringMap($kit?->palette),
        );

        $spacing = array_merge(
            (array) ($defaults['spacing'] ?? []),
            (array) ($kit?->spacing ?? []),
        );

        return [
            'palette' => $palette,
            'fonts'   => $this->resolveFonts($kit?->typography),
            'spacing' => $spacing,
            'logos'   => $this->resolvePaths($kit?->logos),
        ];
    }

    /**
     * Tipografi font yollarını mutlak yola çevirir; eksikse config fontlarına düşer.
     *
     * @return array{regular:string,bold:string}
     */
    private function resolveFonts(mixed $typography): array
    {
        $typography = (array) ($typography ?? []);

        return [
            'regular' => $this->resolvePath($typography['regular'] ?? null)
                ?? (string) config('creative.fonts.regular'),
            'bold' => $this->resolvePath($typography['bold'] ?? null)
                ?? (string) config('creative.fonts.bold'),
        ];
    }

    /**
     * @param  mixed  $map
     * @return array<string,string>
     */
    private function resolvePaths(mixed $map): array
    {
        $out = [];
        foreach ((array) ($map ?? []) as $key => $value) {
            if ($resolved = $this->resolvePath($value)) {
                $out[$key] = $resolved;
            }
        }

        return $out;
    }

    /**
     * Göreli storage yolunu (public disk) mutlak yola çevirir; mutlak/var olan yolu korur.
     */
    private function resolvePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $disk = config('creative.disk', 'public');
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->path($path);
        }

        return null;
    }

    /**
     * Yalnız string değerleri (renk kodları) tutan harita.
     *
     * @return array<string,string>
     */
    private function stringMap(mixed $map): array
    {
        $out = [];
        foreach ((array) ($map ?? []) as $key => $value) {
            if (is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
