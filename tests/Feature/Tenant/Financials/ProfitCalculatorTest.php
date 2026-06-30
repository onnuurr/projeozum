<?php

namespace Tests\Feature\Tenant\Financials;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\MarketplaceCommissionRate;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\ProfitCalculatorService;
use Tests\TestCase;

class ProfitCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_category_specific_rate_over_default(): void
    {
        // Default trendyol komisyonu 18%, kategori-spesifik 10%.
        MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => null,
            'commission_rate' => 18.0, 'shipping_rate' => 4.0,
            'valid_from' => '2026-01-01',
        ]);
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Bluz', 'slug' => 'bluz-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => $catId,
            'commission_rate' => 10.0, 'shipping_rate' => 2.0,
            'valid_from' => '2026-01-01',
        ]);

        $pid = DB::table('products')->insertGetId([
            'name' => 'Bluz X', 'slug' => 'bluz-x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'category_id' => $catId, 'price' => 80, 'purchase_price' => 40,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::find($pid);
        $tenant = Tenant::factory()->create(['settings' => ['vat_rate' => 18]]);

        $service = app(ProfitCalculatorService::class);
        $b = $service->calculate($tenant, $product, null, 'trendyol', sellPrice: 200, qty: 1);

        // sell 200, %10 commission = 20, %2 shipping = 4
        $this->assertEqualsWithDelta(20.0, $b->commission, 0.01);
        $this->assertEqualsWithDelta(4.0,  $b->shipping,   0.01);
    }

    public function test_falls_back_to_default_when_no_category_match(): void
    {
        MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => null,
            'commission_rate' => 18.0, 'shipping_rate' => 4.0,
            'valid_from' => '2026-01-01',
        ]);
        $pid = DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 100, 'purchase_price' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::find($pid);
        $tenant = Tenant::factory()->create();

        $b = app(ProfitCalculatorService::class)
            ->calculate($tenant, $product, null, 'trendyol', sellPrice: 100, qty: 2);

        // sell 200, %18 commission = 36, %4 shipping = 8
        $this->assertEqualsWithDelta(36.0, $b->commission, 0.01);
        $this->assertEqualsWithDelta(8.0,  $b->shipping,   0.01);
    }

    public function test_net_profit_sign(): void
    {
        $pid = DB::table('products')->insertGetId([
            'name' => 'Maliyetli', 'slug' => 'p-' . uniqid(), 'sku' => 'S-' . uniqid(),
            'price' => 500, 'purchase_price' => 300,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::find($pid);
        $tenant = Tenant::factory()->create();

        $loss = app(ProfitCalculatorService::class)
            ->calculate($tenant, $product, null, 'trendyol', sellPrice: 100, qty: 1);

        // Tenant cost 500, sell 100 → kesin zarar.
        $this->assertLessThan(0, $loss->netProfit);
    }
}
