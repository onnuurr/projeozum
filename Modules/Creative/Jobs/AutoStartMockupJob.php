<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupSettings;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Product\Models\Product;
use RuntimeException;

/**
 * Otomatik tetik (Faz 4): ürüne fotoğraf eklendiğinde / bedenleri değiştiğinde (ProductCreated,
 * ProductImagesChanged, ProductVariantsSynced) ürünün EKSİK beden gruplarının mokapı başlatılır — yalnız
 * ayar açıksa (Creative ayarlar › Mokap), kategorisi eşleştirilmişse, yalın fotoğrafı varsa ve günlük hak kalmışsa.
 * Birkaç fotoğraf arka arkaya eklenince tek başlatma olsun diye ürün başına tekil + gecikmeli.
 * Sonuç her zaman onay listesine düşer; hiçbir şey onaysız yayınlanmaz.
 */
class AutoStartMockupJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public int $productId) {}

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    public function handle(MockupSettings $settings, MockupPipelineService $pipeline, ProductOnModelService $onModel): void
    {
        if (! $settings->autoTrigger()) {
            return;
        }
        $product = Product::with(['images', 'category.parent'])->find($this->productId);
        if (! $product || $product->images->isEmpty() || $settings->regionFor($product->category) === null
            || ! $onModel->pickPlainProductPhoto($product)
            || ($pipeline->missingGroups($product) === [] && $pipeline->missingColors($product) === [])) {
            return;
        }

        try {
            $pipeline->startFor($product);
        } catch (RuntimeException $e) {
            // Limit dolu / manken tanımsız vb.: sessizce atlanır, toplu başlatma sonra yakalar.
            Log::info('Mokap otomatik başlatılmadı', ['product_id' => $this->productId, 'reason' => $e->getMessage()]);
        }
    }
}
