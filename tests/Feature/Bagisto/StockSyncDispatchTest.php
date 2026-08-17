<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushStockToBagisto;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;
use Tests\TestCase;

/**
 * Faz 1: `StockService::move()` (StockController/CheckoutService/OrderService
 * gibi tüm stok mutasyonlarının tek noktası) sonrası `StockChanged` event'i
 * gerçekten fırlatılıp Bagisto push job'unu tetikliyor mu.
 */
class StockSyncDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_move_dispatches_stock_push_job(): void
    {
        Queue::fake();

        $variant   = ProductVariant::factory()->create(['stock' => 10]);
        $warehouse = Warehouse::factory()->create();

        app(StockService::class)->move(
            variantId: $variant->id,
            warehouseId: $warehouse->id,
            type: StockMovement::TYPE_IN,
            qty: 5,
        );

        Queue::assertPushed(PushStockToBagisto::class, function ($job) use ($variant) {
            $property = new \ReflectionProperty($job, 'variantId');

            return $property->getValue($job) === $variant->id;
        });
    }
}
