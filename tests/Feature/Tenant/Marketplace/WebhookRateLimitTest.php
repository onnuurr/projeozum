<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bagisto sipariş webhook'u auth'suz — token doğrulaması controller'ın kendi içinde. Öncesinde
 * hiçbir hız sınırı yoktu: doğrulamadan ÖNCE her istek DB sorgusu tetikleyebiliyordu, yani
 * auth'suz bir DoS/kaynak tüketim yüzeyiydi. Modules/Bagisto/routes/api.php'ye throttle:30,1
 * eklendi; bu test korumanın çalıştığını doğrular. (Pazaryeri webhook uçları 2026-09-22'de
 * Bayi Portalı/pazaryeri senkronuyla birlikte kaldırıldı — c4b0bd6.)
 */
class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bagisto_order_webhook_is_rate_limited_after_30_requests_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/webhooks/bagisto', [])->assertStatus(401);
        }

        $this->postJson('/api/webhooks/bagisto', [])->assertStatus(429);
    }
}
