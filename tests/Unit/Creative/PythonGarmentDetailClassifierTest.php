<?php

namespace Tests\Unit\Creative;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Enhancement\PythonGarmentDetailClassifier;
use Tests\TestCase;

/**
 * PythonGarmentDetailClassifier hiçbir durumda pipeline'ı bozmamalı: başarılı
 * çıktıda doğru path->labels eşlemesini kurar, her hata türünde (non-zero exit,
 * bozuk JSON) sessizce boş öneri listesine düşer.
 */
class PythonGarmentDetailClassifierTest extends TestCase
{
    public function test_maps_successful_json_output_by_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'detail');
        file_put_contents($path, 'x');

        Process::fake([
            '*' => Process::result(json_encode([
                'results' => [
                    ['path' => $path, 'labels' => [['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82]]],
                ],
            ])),
        ]);

        $result = (new PythonGarmentDetailClassifier())->classify([$path]);

        $this->assertSame([
            ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
        ], $result[$path]);

        unlink($path);
    }

    public function test_degrades_to_empty_labels_on_non_zero_exit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'detail');
        file_put_contents($path, 'x');

        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'model yüklenemedi', exitCode: 1),
        ]);
        Log::shouldReceive('warning')->once();

        $result = (new PythonGarmentDetailClassifier())->classify([$path]);

        $this->assertSame([], $result[$path]);

        unlink($path);
    }

    public function test_degrades_to_empty_labels_on_malformed_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'detail');
        file_put_contents($path, 'x');

        Process::fake(['*' => Process::result(output: 'not json')]);
        Log::shouldReceive('warning')->once();

        $result = (new PythonGarmentDetailClassifier())->classify([$path]);

        $this->assertSame([], $result[$path]);

        unlink($path);
    }

    public function test_returns_empty_array_when_no_valid_paths(): void
    {
        $result = (new PythonGarmentDetailClassifier())->classify(['/no/such/file.jpg']);

        $this->assertSame([], $result);
    }
}
