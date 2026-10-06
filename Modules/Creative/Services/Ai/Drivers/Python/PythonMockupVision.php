<?php

namespace Modules\Creative\Services\Ai\Drivers\Python;

use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use RuntimeException;

/**
 * Mokap görüntü adımlarını proje sanal ortamındaki Python betikleriyle çalıştırır
 * (config creative.mockup.python_bin / scripts). Betikler stdout'a tek satır JSON yazar
 * ({"ok": true, ...} | {"ok": false, "error": "..."}). Her çağrı ayrı süreçtir: torch/MediaPipe
 * kuyruk worker'ının belleğine yüklenmez.
 */
class PythonMockupVision implements MockupVisionContract
{
    public function segment(string $imagePath, string $labelsPath): array
    {
        return $this->run('segment', [$imagePath, $labelsPath]);
    }

    public function mask(string $labelsPath, string $prefix, string $region, string $length): array
    {
        return $this->run('mask', ['mask', $labelsPath, $prefix, $region, $length]);
    }

    public function pair(string $leftPath, string $rightPath, string $outPath): void
    {
        $this->run('mask', ['pair', $leftPath, $rightPath, $outPath]);
    }

    public function upscale(string $imagePath, string $outPath, int $width, int $height): void
    {
        $this->run('upscale', [$imagePath, $outPath, (string) $width, (string) $height]);
    }

    public function crop(string $imagePath, array $box, string $outPath): void
    {
        $this->run('mask', ['crop', $imagePath, ...array_map('strval', $box), $outPath]);
    }

    public function closeupBox(string $compositePath, string $basePath, string $labelsPath, string $shot): array
    {
        return $this->run('mask', ['closeup', $compositePath, $basePath, $labelsPath, $shot]);
    }

    public function composite(string $basePath, string $modelOutPath, string $prefix, array $box, string $resultPath, bool $restoreShoes): array
    {
        return $this->run('composite', [$basePath, $modelOutPath, $prefix, ...array_map('strval', $box), $resultPath, $restoreShoes ? '1' : '0']);
    }

    /**
     * @param  array<int,string>  $args
     * @return array<string,mixed>
     */
    private function run(string $script, array $args): array
    {
        $result = Process::timeout((int) config('creative.mockup.timeout', 300))
            ->path(base_path('Modules/Creative/python'))
            ->run([(string) config('creative.mockup.python_bin'), (string) config("creative.mockup.scripts.{$script}"), ...$args]);

        $lines = array_values(array_filter(explode("\n", trim($result->output()))));
        $json  = json_decode((string) end($lines), true);

        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            $error = is_array($json) ? ($json['error'] ?? '') : '';
            throw new RuntimeException(__('creative::messages.mockup.vision_failed', [
                'step'  => $script,
                'error' => $error ?: (trim($result->errorOutput()) ?: 'exit ' . $result->exitCode()),
            ]));
        }

        unset($json['ok']);

        return $json;
    }
}
