<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;
use Tests\TestCase;

/**
 * Faz 4: StockService::move() eski StockController::movement() davranışıyla parite —
 * tek stok mutasyon noktası (lock → stok → movement satırı → variant cache resync).
 */
class StockServiceMoveTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $qty = 10): array
    {
        $variant   = ProductVariant::factory()->create(['stock' => $qty]);
        $warehouse = Warehouse::factory()->create();
        $stock     = Stock::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'quantity'           => $qty,
        ]);

        return [$variant, $warehouse, $stock];
    }

    public function test_in_movement_increments_stock_and_resyncs_variant_cache(): void
    {
        [$variant, $warehouse, $stock] = $this->scenario(10);

        $movement = app(StockService::class)->move(
            variantId: $variant->id, warehouseId: $warehouse->id,
            type: StockMovement::TYPE_IN, qty: 5,
        );

        $this->assertSame(15, $stock->fresh()->quantity);
        $this->assertSame(10, $movement->before_quantity);
        $this->assertSame(15, $movement->after_quantity);
        $this->assertSame(5, $movement->quantity);
        // Denormalize variant cache resync (karar #4).
        $this->assertSame(15, (int) $variant->fresh()->stock);
    }

    public function test_out_movement_decrements_and_writes_signed_quantity(): void
    {
        [$variant, $warehouse, $stock] = $this->scenario(10);

        $movement = app(StockService::class)->move(
            variantId: $variant->id, warehouseId: $warehouse->id,
            type: StockMovement::TYPE_OUT, qty: 3,
        );

        $this->assertSame(7, $stock->fresh()->quantity);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, (int) $variant->fresh()->stock);
    }

    public function test_out_below_zero_is_blocked(): void
    {
        [$variant, $warehouse, $stock] = $this->scenario(2);

        try {
            app(StockService::class)->move(
                variantId: $variant->id, warehouseId: $warehouse->id,
                type: StockMovement::TYPE_OUT, qty: 5,
            );
            $this->fail('Negatif stok koruması bekleniyordu.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // Hiçbir mutasyon kalıcı olmadı.
        $this->assertSame(2, $stock->fresh()->quantity);
        $this->assertSame(0, StockMovement::where('product_variant_id', $variant->id)->count());
    }
}
