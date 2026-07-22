<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\CompositionRequest;
use Modules\Creative\Services\Ai\Contracts\CompositionComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * fal.ai anahtarı yokken devreye giren placeholder kompozisyon sürücüsü.
 *
 * İstenen headline'ı MockImageFactory'nin sabit (16,16) konumuna basar —
 * bu sayede LayoutConstraintEngine + OCR ucu, gerçek bir fal.ai çağrısı
 * yapılmadan uçtan uca test edilebilir.
 */
class MockCompositionComposer implements CompositionComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(CompositionRequest $request): string
    {
        $color = $request->palette['primary'] ?? '#3b4252';
        $label = $request->headline ?: ('MOCK COMPOSE: ' . $request->productName);

        $bytes = $this->images->make(1080, 1350, $color, $label);

        return ImageFile::temp($bytes, 'png');
    }

    public function modelIdentifier(): string
    {
        return 'mock';
    }
}
