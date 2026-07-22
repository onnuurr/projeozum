<?php

namespace Modules\Product\Services;

use Modules\Product\Enums\Capability;
use Modules\Product\Exceptions\ProductNotReadyException;
use Modules\Product\Models\Product;

/**
 * Ürünün belirli bir akış (try-on, kreatif render, pazaryeri push) için
 * "hazır" sayılıp sayılmadığını tek bir yerden cevaplar (Faz 4). Yalnızca
 * Product'ın kendi verisine bakar (görsel/varyant/kategori-pazaryeri eşleme)
 * — Creative'in çalışma zamanı seçimleri (manken/poz) burada YOK, onlar
 * ürün tamlığı değil.
 *
 * Product burada yalnızca SORULDUĞUNDA cevap verir; hiçbir akışı kendiliğinden
 * engellemez (enforcement çağıran modülün kararı, bkz. roadmap'te reddedilen
 * "Product Orchestrator" deseni).
 */
class ProductReadinessService
{
    /**
     * @return array{ready: bool, score: int, missing: array<int, string>}
     */
    public function evaluate(Product $product, Capability $capability): array
    {
        $checks = match ($capability) {
            Capability::TryOn, Capability::CreativeRender => [
                ...$this->imageChecks($product),
            ],
            Capability::MarketplacePush => [
                ...$this->imageChecks($product),
                ...$this->variantChecks($product),
                ...$this->marketplaceMappingChecks($product),
            ],
        };

        $total   = $this->totalChecksFor($capability);
        $missing = $checks;

        return [
            'ready'   => $missing === [],
            'score'   => $total > 0 ? (int) round((($total - count($missing)) / $total) * 100) : 100,
            'missing' => $missing,
        ];
    }

    /**
     * @throws ProductNotReadyException
     */
    public function assertReady(Product $product, Capability $capability): void
    {
        $result = $this->evaluate($product, $capability);

        if (! $result['ready']) {
            throw new ProductNotReadyException($capability, $result['missing']);
        }
    }

    private function totalChecksFor(Capability $capability): int
    {
        return match ($capability) {
            Capability::TryOn, Capability::CreativeRender => 1,
            Capability::MarketplacePush => 3,
        };
    }

    /**
     * @return array<int, string>
     */
    private function imageChecks(Product $product): array
    {
        return $product->images->isEmpty() ? ['En az bir ürün görseli gerekli'] : [];
    }

    /**
     * @return array<int, string>
     */
    private function variantChecks(Product $product): array
    {
        $hasUsableVariant = $product->variants->contains(
            fn ($v) => filled($v->sku) && (int) $v->stock > 0,
        );

        return $hasUsableVariant ? [] : ['En az bir varyant (SKU + stok) gerekli'];
    }

    /**
     * @return array<int, string>
     */
    private function marketplaceMappingChecks(Product $product): array
    {
        $hasMapping = $product->category?->marketplaceMappings?->isNotEmpty() ?? false;

        return $hasMapping ? [] : ['Kategori için pazaryeri eşlemesi tanımlı değil'];
    }
}
