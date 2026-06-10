<?php

namespace Modules\Creative\Services\Rendering;

use Modules\Creative\Models\CreativeTemplate;

interface RendererContract
{
    /**
     * SVG şablonu inceler; boyut ve slot tanımlarını döndürür.
     *
     * @return array{width:int,height:int,slots:array<int,array<string,mixed>>}
     */
    public function inspect(string $svgPath): array;

    /**
     * Tasarımcıdan gelen slot tanımlarını SVG dosyasına geri yazar.
     * (Render motoru slotları SVG'den okuduğu için kalıcılık SVG'de olmalıdır.)
     *
     * @param  array<int,array<string,mixed>>  $slots
     */
    public function applySlots(string $svgPath, array $slots): void;

    /**
     * Şablonu verilen değerler ve yerel görsel yollarıyla render eder.
     *
     * @param  array<string,string>  $values      slot anahtarı => metin (örn. ['product_name' => 'Tişört'])
     * @param  array<string,string>  $imagePaths  slot anahtarı => yerel dosya yolu
     * @param  array<string,mixed>   $brand       BrandTokenService çıktısı (palette/fonts/spacing/logos)
     * @return string  Görsel baytları (PNG)
     */
    public function render(CreativeTemplate $template, array $values, array $imagePaths, array $brand = []): string;
}
