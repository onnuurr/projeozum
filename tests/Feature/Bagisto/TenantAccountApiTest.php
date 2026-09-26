<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Services\TenantCreditService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bayi portalı "Cari Hesap" ekranının kaynağı: GET /api/bagisto/tenant-accounts/statement.
 */
class TenantAccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bagisto.inbound.token' => 'test-token']);
    }

    private function tenantWithUser(string $email, array $attributes = []): Tenant
    {
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        $tenant = Tenant::factory()->create($attributes);

        User::factory()->create(['tenant_id' => $tenant->id, 'email' => $email])->assignRole('tenant');

        return $tenant;
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/bagisto/tenant-accounts/statement?email=magaza@example.com')->assertStatus(401);
    }

    public function test_returns_404_for_unknown_email(): void
    {
        $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/bagisto/tenant-accounts/statement?email=yok@example.com')
            ->assertStatus(404);
    }

    public function test_returns_account_summary_open_invoices_and_ledger_entries(): void
    {
        $tenant = $this->tenantWithUser('magaza@example.com', ['credit_limit' => 10000, 'current_balance' => 0]);
        $other = $this->tenantWithUser('baska@example.com', ['credit_limit' => 5000, 'current_balance' => 0]);

        $order = Order::factory()->forTenant($tenant)->create(['bagisto_order_id' => '4242', 'total' => 800]);

        $credit = app(TenantCreditService::class);
        $credit->charge($tenant, 800, 'bagisto_order', orderId: $order->id);
        $credit->charge($other, 300, 'bagisto_order');

        TenantInvoice::create(['tenant_id' => $tenant->id, 'amount' => 800, 'due_date' => now()->subDay()->toDateString()]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/bagisto/tenant-accounts/statement?email=magaza@example.com')
            ->assertOk();

        $response->assertJsonPath('account.credit_limit', 10000)
            ->assertJsonPath('account.current_balance', 800)
            ->assertJsonPath('account.available', 9200)
            ->assertJsonCount(1, 'open_invoices')
            ->assertJsonPath('open_invoices.0.overdue', true)
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.type', 'debit')
            ->assertJsonPath('entries.0.reason_label', 'Sipariş')
            ->assertJsonPath('entries.0.bagisto_order_id', '4242')
            ->assertJsonPath('meta.total', 1);
    }
}
