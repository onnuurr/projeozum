<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Creative\Models\BrandKit;
use Modules\Creative\Models\CreativeTemplate;

/**
 * Marka kitinden (palette/typography/logo) otomatik SVG şablon üretir.
 *
 * Sabit "layout preset"lerin (config('creative.template_presets')) oransal
 * slot geometrisini seçilen sosyal formatın (config('creative.formats'))
 * piksel boyutuna uygular, data-slot sözleşimine uygun bir SVG string'i
 * kurar ve manuel yükleme akışıyla aynı yolu izler: dosyayı diske yazar,
 * CreativeTemplate satırı açar, CreativeRenderService::inspectTemplate() ile
 * (Python inspect script'i üzerinden) width/height/slots'u doldurtur — slot
 * ayrıştırma mantığı burada tekrarlanmaz.
 *
 * Metin slotlarının fill'i literal "token:<palette_key>" olarak yazılır;
 * bunu render motoru (python/render.py::resolve_color_token) her render'da
 * güncel marka paletinden çözer, yani marka kiti sonradan değişirse üretilen
 * şablonlar da otomatik güncel kalır. Dekoratif (data-slot taşımayan) arka
 * plan şekilleri slot sözleşiminin dışında olduğu için (yalnız data-slot
 * elemanları render motorunda token çözümlemesinden geçer) üretim anında
 * paletten çözülmüş gerçek hex renkle gömülür.
 */
class TemplateGeneratorService
{
    private const PLACEHOLDER_TEXT = [
        'headline'     => 'Başlık Buraya',
        'sub_headline' => 'Alt başlık metni',
        'cta_button'   => 'Şimdi Keşfet',
    ];

    public function __construct(
        private CreativeRenderService $renderService,
        private BrandTokenService $brandTokens,
    ) {}

    /**
     * UI'da seçim listesi için preset key => label.
     *
     * @return array<string,string>
     */
    public function presets(): array
    {
        return collect((array) config('creative.template_presets', []))
            ->map(fn (array $preset) => (string) ($preset['label'] ?? ''))
            ->all();
    }

    /**
     * Verilen marka kiti + preset + format için tek bir CreativeTemplate üretir.
     */
    public function generate(BrandKit $kit, string $presetKey, string $formatKey): CreativeTemplate
    {
        $presets = (array) config('creative.template_presets', []);
        $formats = (array) config('creative.formats', []);

        if (! isset($presets[$presetKey]) || ! is_array($presets[$presetKey])) {
            throw new InvalidArgumentException("Bilinmeyen şablon preset'i: {$presetKey}");
        }
        if (! isset($formats[$formatKey]) || ! is_array($formats[$formatKey])) {
            throw new InvalidArgumentException("Bilinmeyen sosyal format: {$formatKey}");
        }

        $preset = $presets[$presetKey];
        $format = $formats[$formatKey];
        $width  = (int) $format['width'];
        $height = (int) $format['height'];

        $palette = (array) ($this->brandTokens->tokensFor($kit)['palette'] ?? []);

        $svgPath = $this->storeSvg(
            $this->buildSvg((array) $preset['slots'], $width, $height, $palette),
        );

        $template = CreativeTemplate::create([
            'name'      => sprintf('%s · %s · %s', $kit->name, $preset['label'], $format['label'] ?? $formatKey),
            'svg_path'  => $svgPath,
            'width'     => $width,
            'height'    => $height,
            'slots'     => [],
            'is_active' => true,
        ]);

        return $this->renderService->inspectTemplate($template);
    }

    /**
     * Preset slot tanımlarından (0-1 oranlı) tam bir SVG belgesi kurar.
     *
     * @param  array<int,array<string,mixed>>  $slots
     * @param  array<string,string>  $palette
     */
    private function buildSvg(array $slots, int $width, int $height, array $palette): string
    {
        $body = '';
        foreach ($slots as $slot) {
            $body .= match ($slot['type'] ?? null) {
                'text'  => $this->textElement($slot, $width, $height),
                'image' => $this->imageElement($slot, $width, $height),
                'rect'  => $this->decorativeRectElement($slot, $width, $height, $palette),
                default => '',
            };
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s</svg>',
            $width,
            $height,
            $width,
            $height,
            $body,
        );
    }

    /**
     * @param  array<string,mixed>  $slot
     */
    private function textElement(array $slot, int $width, int $height): string
    {
        $key      = (string) $slot['key'];
        $x        = round(((float) $slot['x']) * $width, 2);
        $y        = round(((float) $slot['y']) * $height, 2);
        $fontSize = round(((float) $slot['font_size']) * $height, 2);
        $anchor   = ['left' => 'start', 'center' => 'middle', 'right' => 'end'][$slot['align'] ?? 'left'] ?? 'start';
        $weight   = ($slot['bold'] ?? false) ? 'bold' : 'normal';
        $fill     = (string) ($slot['fill'] ?? '#000000');
        // Sağ kenara kadar kalan alan: CreativeCopyRuleEngine'in karakter-tavanı
        // denetimi için (bkz. python/_svgcommon.py::iter_slots "data-w").
        $availableW = round(($width - $x) * 0.92, 2);
        $placeholder = self::PLACEHOLDER_TEXT[$key] ?? $key;

        return sprintf(
            '<text data-slot="%s" x="%s" y="%s" font-size="%s" text-anchor="%s" font-weight="%s" fill="%s" data-w="%s">%s</text>',
            $this->esc($key),
            $x,
            $y,
            $fontSize,
            $anchor,
            $weight,
            $this->esc($fill),
            $availableW,
            $this->esc($placeholder),
        );
    }

    /**
     * @param  array<string,mixed>  $slot
     */
    private function imageElement(array $slot, int $width, int $height): string
    {
        $x   = round(((float) $slot['x']) * $width, 2);
        $y   = round(((float) $slot['y']) * $height, 2);
        $w   = round(((float) $slot['w']) * $width, 2);
        $h   = round(((float) $slot['h']) * $height, 2);
        $fit = (string) ($slot['fit'] ?? 'cover');

        return sprintf(
            '<rect data-slot="%s" x="%s" y="%s" width="%s" height="%s" data-fit="%s" fill="#e5e7eb"/>',
            $this->esc((string) $slot['key']),
            $x,
            $y,
            $w,
            $h,
            $this->esc($fit),
        );
    }

    /**
     * data-slot TAŞIMAZ (render motoru yalnız data-slot elemanlarını renk
     * token'ı için çözer) — bu yüzden "token:" öneki burada, üretim anında,
     * verilen paletten gerçek hex'e çözülür.
     *
     * @param  array<string,mixed>  $slot
     * @param  array<string,string>  $palette
     */
    private function decorativeRectElement(array $slot, int $width, int $height, array $palette): string
    {
        $x = round(((float) $slot['x']) * $width, 2);
        $y = round(((float) $slot['y']) * $height, 2);
        $w = round(((float) $slot['w']) * $width, 2);
        $h = round(((float) $slot['h']) * $height, 2);
        $fill = $this->resolveFill((string) ($slot['fill'] ?? '#000000'), $palette);

        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>',
            $x,
            $y,
            $w,
            $h,
            $this->esc($fill),
        );
    }

    /**
     * @param  array<string,string>  $palette
     */
    private function resolveFill(string $fill, array $palette): string
    {
        if (str_starts_with($fill, 'token:')) {
            return $palette[substr($fill, 6)] ?? '#000000';
        }

        return $fill;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }

    private function storeSvg(string $svg): string
    {
        $path = sprintf('creative_templates/generated/%s.svg', (string) Str::uuid());

        Storage::disk($this->disk())->put($path, $svg);

        return $path;
    }

    private function disk(): string
    {
        return (string) config('creative.disk', 'public');
    }
}
