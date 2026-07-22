<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\Support\CopyConstraints;
use Modules\Creative\Services\Ai\Support\CopySlots;

/**
 * Gemini anahtarı yokken devreye giren şablon tabanlı copy sürücüsü.
 *
 * Her istenen slot için rolüne (headline/sub/cta/body) uygun, ürün adını içeren
 * makul bir metin üretir; CTA slotunda markanın tercih ettiği ilk ifadeyi kullanır.
 * Çıktı yine CopyConstraints'ten geçer (yasaklı kelime güvencesi tek noktada).
 */
class MockCopyGenerator implements CopyGeneratorContract
{
    /**
     * @return array<string,string>
     */
    public function generate(CopyRequest $request): array
    {
        $name    = trim($request->productName) !== '' ? trim($request->productName) : 'Yeni ürün';
        $cta     = $request->ctaPhrases[0] ?? 'Hemen keşfet';

        $out = [];
        foreach ($request->slotKeys as $key) {
            $out[$key] = match (CopySlots::roleFor($key)) {
                'headline'    => $name,
                'subheadline' => 'Yeni sezonun öne çıkan parçası',
                'cta'         => $cta,
                default       => "{$name} şimdi koleksiyonda.",
            };
        }

        return CopyConstraints::sanitize($out, $request->bannedWords);
    }
}
