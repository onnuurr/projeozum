<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Tenant\Jobs\Marketplace\Trendyol\ProcessTrendyolWebhookJob;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;
use Tests\TestCase;

class TrendyolWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function setupCred(string $supplierId, string $apiSecret): TenantMarketplaceCredential
    {
        $tenant = Tenant::factory()->create();

        return TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'supplier_id' => $supplierId,
            'api_key'     => 'k',
            'api_secret'  => $apiSecret,
            'is_active'   => true,
        ]);
    }

    public function test_missing_supplier_returns_400(): void
    {
        $this->postJson('/api/webhooks/trendyol', [])->assertStatus(400);
    }

    public function test_unknown_supplier_returns_404(): void
    {
        $this->withHeaders(['X-Trendyol-Supplier-Id' => 'YOK'])
            ->postJson('/api/webhooks/trendyol', [])
            ->assertStatus(404);
    }

    public function test_invalid_hmac_returns_401(): void
    {
        $cred = $this->setupCred('SUP-100', 'secret');

        $body = json_encode(['orderNumber' => 'TY-1']);
        // Yanlış imza
        $this->call(
            'POST',
            '/api/webhooks/trendyol',
            [], [], [],
            [
                'HTTP_X-Trendyol-Supplier-Id' => 'SUP-100',
                'HTTP_X-Trendyol-Signature'   => 'BAD',
                'CONTENT_TYPE'                => 'application/json',
            ],
            $body,
        )->assertStatus(401);
    }

    public function test_valid_hmac_dispatches_job(): void
    {
        Bus::fake([ProcessTrendyolWebhookJob::class]);

        $cred = $this->setupCred('SUP-200', 'top-secret');
        $body = json_encode(['orderNumber' => 'TY-1', 'lines' => []]);
        $signature = hash_hmac('sha256', $body, 'top-secret');

        $this->call(
            'POST',
            '/api/webhooks/trendyol',
            [], [], [],
            [
                'HTTP_X-Trendyol-Supplier-Id' => 'SUP-200',
                'HTTP_X-Trendyol-Signature'   => $signature,
                'CONTENT_TYPE'                => 'application/json',
            ],
            $body,
        )->assertStatus(202);

        Bus::assertDispatched(ProcessTrendyolWebhookJob::class, fn ($j) => $j->tenantId === $cred->tenant_id);
    }
}
