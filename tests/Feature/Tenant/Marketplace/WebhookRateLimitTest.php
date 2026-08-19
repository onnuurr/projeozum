<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pazaryeri webhook uçları (trendyol/hepsiburada/n11/ciceksepeti) ve Bagisto sipariş
 * webhook'u auth'suz — imza/token doğrulaması her controller'ın kendi içinde. Öncesinde
 * hiçbir hız sınırı yoktu: imza kontrolünden ÖNCE her istek en az bir DB sorgusu
 * tetikliyordu (bkz. TrendyolWebhookController), yani auth'suz bir DoS/kaynak tüketim
 * yüzeyiydi. Modules/Tenant/routes/api.php ve Modules/Bagisto/routes/api.php'ye
 * throttle:30,1 eklendi; bu test korumanın çalıştığını doğrular.
 */
class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_trendyol_webhook_is_rate_limited_after_30_requests_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/webhooks/trendyol', [])->assertStatus(400);
        }

        $this->postJson('/api/webhooks/trendyol', [])->assertStatus(429);
    }

    public function test_bagisto_order_webhook_is_rate_limited_after_30_requests_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/webhooks/bagisto', [])->assertStatus(401);
        }

        $this->postJson('/api/webhooks/bagisto', [])->assertStatus(429);
    }
}
