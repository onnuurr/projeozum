<?php

namespace Modules\Creative\Services\Rendering;

use App\Support\Media;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Models\CreativeTemplate;
use RuntimeException;

class PythonRenderer implements RendererContract
{
    public function inspect(string $svgPath): array
    {
        $payload = [
            'svg_path'  => $svgPath,
            'resvg_bin' => $this->resvgBin(),
        ];

        $out = $this->run($this->script('inspect'), $payload, asJson: true);

        return [
            'width'  => (int) ($out['width'] ?? 0),
            'height' => (int) ($out['height'] ?? 0),
            'slots'  => $out['slots'] ?? [],
        ];
    }

    public function applySlots(string $svgPath, array $slots): void
    {
        $this->run($this->script('apply'), [
            'svg_path' => $svgPath,
            'slots'    => array_values($slots),
        ], asJson: true);
    }

    public function render(CreativeTemplate $template, array $values, array $imagePaths, array $brand = [], ?int $outWidth = null, ?int $outHeight = null): string
    {
        // Şablon 'logo' slotu tanımlıyorsa ve çağıran taraf ayrıca bir logo
        // vermediyse, marka kitinin birincil logosu otomatik enjekte edilir.
        if (! isset($imagePaths['logo'])) {
            $brandLogo = is_array($brand['logos'] ?? null) ? ($brand['logos']['primary'] ?? null) : null;
            if (is_string($brandLogo) && $brandLogo !== '') {
                $imagePaths['logo'] = $brandLogo;
            }
        }

        // Brand kit fontları (varsa) config fontlarının yerine geçer.
        $brandFonts = is_array($brand['fonts'] ?? null) ? $brand['fonts'] : [];
        $fonts = [
            'regular' => $brandFonts['regular'] ?? config('creative.fonts.regular'),
            'bold'    => $brandFonts['bold'] ?? config('creative.fonts.bold'),
        ];

        $payload = [
            'svg_path'  => $this->resolveSvgPath($template->svg_path),
            'width'     => (int) $template->width,
            'height'    => (int) $template->height,
            'slots'     => $template->slots ?? [],
            'values'    => $values,
            'images'    => $imagePaths,
            'fonts'     => $fonts,
            // Renk token'ları: slot fill'i "token:primary" gibiyse Python paletten çözer.
            'palette'   => is_array($brand['palette'] ?? null) ? $brand['palette'] : [],
            'resvg_bin' => $this->resvgBin(),
            'mime'      => 'image/png',
            // Nihai çıktı boyutu (sosyal format); verilirse canvas buna "cover" uydurulur.
            'output_width'  => $outWidth,
            'output_height' => $outHeight,
        ];

        return $this->run($this->script('render'), $payload, asJson: false);
    }

    /**
     * Python betiğini stdin'e JSON vererek çalıştırır.
     *
     * @return ($asJson is true ? array<string,mixed> : string)
     */
    private function run(string $script, array $payload, bool $asJson)
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $result = Process::timeout($this->timeout())
            ->input($json)
            ->run([$this->pythonBin(), $script]);

        if ($result->failed()) {
            throw new RuntimeException(sprintf(
                'Creative render başarısız (exit %d): %s',
                $result->exitCode() ?? -1,
                trim($result->errorOutput()) ?: 'bilinmeyen hata'
            ));
        }

        $output = $result->output();

        if ($asJson) {
            $decoded = json_decode($output, true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Python betiği geçerli JSON döndürmedi: ' . substr($output, 0, 500));
            }

            return $decoded;
        }

        if ($output === '') {
            throw new RuntimeException('Python betiği boş çıktı üretti.');
        }

        return $output;
    }

    /**
     * Şablonun svg_path'i public-disk göreli saklanır; Python mutlak yol ister.
     */
    private function resolveSvgPath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        // Uzak disk (R2/S3) ise yerel cache'e indirir; çözülemezse göreli yolu
        // döndürür (Python net bir "dosya yok" hatasıyla başarısız olur).
        return Media::localPath($path, config('creative.disk', 'public')) ?? $path;
    }

    private function script(string $key): string
    {
        $path = config("creative.render.scripts.$key");
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException("Creative render betiği bulunamadı: $key ($path)");
        }

        return $path;
    }

    private function pythonBin(): string
    {
        return (string) config('creative.render.python_bin', 'python3');
    }

    private function resvgBin(): string
    {
        return (string) config('creative.render.resvg_bin', '');
    }

    private function timeout(): int
    {
        return (int) config('creative.render.timeout', 120);
    }
}
