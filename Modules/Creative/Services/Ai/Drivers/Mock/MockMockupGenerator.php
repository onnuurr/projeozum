<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken (dev/test) mokap AI adımlarını taklit eder: sabit bir kombin önerir,
 * görselleri kopyalar. Pipeline'ın geri kalanı (segmentasyon, maske, birleştirme, onay) gerçek çalışır.
 */
class MockMockupGenerator implements MockupGeneratorContract
{
    public function __construct(private MockImageFactory $images) {}

    public function stylist(string $productImagePath, array $context): array
    {
        return [
            'product_summary' => 'mock product',
            'outfit_pieces'   => ['top' => 'white tee', 'footwear' => 'white sneakers'],
            'outfit_sentence' => 'A fitted white t-shirt and white sneakers.',
            'poses'           => array_slice(array_keys($context['poses']), 0, $context['extra_poses']),
            'reasoning_tr'    => 'Test kombini.',
        ];
    }

    public function masterBase(string $mannequinReferencePath, string $poseTemplatePath, array $outfit): string
    {
        return $this->copy($mannequinReferencePath, 'MOCK BASE');
    }

    public function derivedBase(string $masterPath, string $poseTemplatePath, array $outfit): string
    {
        return $this->copy($masterPath, 'MOCK BASE');
    }

    public function tryOn(string $cropPath, array $productImagePaths, string $region, string $length, ?string $correction = null): string
    {
        return $this->copy($cropPath, 'MOCK TRY-ON');
    }

    public function checkBaseConsistency(string $pairPath): array
    {
        return ['verdict' => 'pass', 'issues' => []];
    }

    public function checkFidelity(string $pairPath, string $region, string $view): array
    {
        return ['verdict' => 'pass', 'issues' => []];
    }

    public function modelIdentifier(): string
    {
        return 'mock';
    }

    private function copy(string $path, string $label): string
    {
        $bin = is_file($path) ? (string) file_get_contents($path) : '';

        return ImageFile::temp($bin !== '' ? $bin : $this->images->make(768, 1024, '#2e3440', $label), 'png');
    }
}
