<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;
use Tests\TestCase;

/**
 * (Eskiden CheckoutStockTest'in parçasıydı; checkout 2026-09-22'de sepetle birlikte
 * kaldırılınca yalnızca hâlâ geçerli olan stok hesabı testi buraya taşındı.)
 */
class StockAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_for_variant_excludes_reserved(): void
    {
        $variant = ProductVariant::factory()->create();
        $wh      = Warehouse::factory()->create();
        Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $wh->id,
            'quantity' => 10, 'reserved_quantity' => 4,
        ]);

        $this->assertSame(6, app(StockService::class)->availableForVariant($variant->id));
    }
}
