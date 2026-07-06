<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\MarketplaceCommissionRate;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;
use Tests\TestCase;

class CommissionLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): AbstractMarketplaceService
    {
        $tenant = Tenant::factory()->create();
        $cred = TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'is_active'   => true,
        ]);

        return new class($cred) extends AbstractMarketplaceService {
            public function code(): string { return 'trendyol'; }
            public function pushProduct(Product $product): PushResult { return new PushResult(true, 1); }
            public function fetchOrders(\DateTimeInterface $since): Collection { return collect(); }
            public function fetchReports(\DateTimeInterface $from, \DateTimeInterface $to): ReportResult {
                return new ReportResult(\DateTimeImmutable::createFromInterface($from), \DateTimeImmutable::createFromInterface($to), 0, 0, 0);
            }
            public function verifyWebhook(Request $request): bool { return true; }

            // Expose protected lookupRates for test.
            public function publicLookup(string $marketplace, int $categoryId): array
            {
                return $this->lookupRates($marketplace, $categoryId);
            }
        };
    }

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

        $service = $this->makeService();

        [$comm, $ship] = $service->publicLookup('trendyol', $catId);
        $this->assertSame(10.0, $comm);
        $this->assertSame(2.0, $ship);

        // Bilinmeyen kategori → default'a düşer.
        [$comm2, $ship2] = $service->publicLookup('trendyol', 99999);
        $this->assertSame(18.0, $comm2);
        $this->assertSame(4.0, $ship2);
    }

    public function test_no_rate_returns_zero(): void
    {
        $service = $this->makeService();
        [$comm, $ship] = $service->publicLookup('hepsiburada', 0);
        $this->assertSame(0.0, $comm);
        $this->assertSame(0.0, $ship);
    }
}
