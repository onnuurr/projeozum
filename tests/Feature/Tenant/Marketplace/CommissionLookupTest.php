<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\MarketplaceCommissionRate;
use Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService;
use Tests\TestCase;

class CommissionLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_specific_rate_beats_default(): void
    {
        // Default Trendyol = 18% commission
        MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => null,
            'commission_rate' => 18.0, 'shipping_rate' => 4.0,
            'valid_from' => '2026-01-01',
        ]);

        // Kategori-spesifik override (Bluz=10) — daha iyi oran.
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Bluz', 'slug' => 'bluz-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => $catId,
            'commission_rate' => 10.0, 'shipping_rate' => 2.0,
            'valid_from' => '2026-01-01',
        ]);

        $sync = new TenantMarketplaceSyncService();

        [$comm, $ship] = $sync->lookupRates('trendyol', $catId);
        $this->assertSame(10.0, $comm);
        $this->assertSame(2.0, $ship);

        // Bilinmeyen kategori → default'a düşer.
        [$comm2, $ship2] = $sync->lookupRates('trendyol', 99999);
        $this->assertSame(18.0, $comm2);
        $this->assertSame(4.0, $ship2);
    }

    public function test_no_rate_returns_zero(): void
    {
        $sync = new TenantMarketplaceSyncService();
        [$comm, $ship] = $sync->lookupRates('hepsiburada', 0);
        $this->assertSame(0.0, $comm);
        $this->assertSame(0.0, $ship);
    }
}
