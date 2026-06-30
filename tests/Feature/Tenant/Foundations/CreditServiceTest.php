<?php

namespace Tests\Feature\Tenant\Foundations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Modules\Tenant\Services\TenantCreditService;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantCreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantCreditService::class);
    }

    public function test_charge_within_limit_creates_ledger_and_updates_balance(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit'    => 1000,
            'current_balance' => 0,
        ]);

        $ledger = $this->service->charge($tenant, 250.50, 'order', orderId: null);

        $this->assertInstanceOf(TenantCreditLedger::class, $ledger);
        $this->assertSame(TenantCreditLedger::TYPE_DEBIT, $ledger->type);
        $this->assertEquals(250.50, (float) $ledger->amount);
        $this->assertEquals(250.50, (float) $ledger->balance_after);
        $this->assertEquals(250.50, (float) $tenant->fresh()->current_balance);
    }

    public function test_charge_over_limit_throws(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit'    => 500,
            'current_balance' => 400,
        ]);

        $this->expectException(InsufficientCreditException::class);
        $this->service->charge($tenant, 200, 'order');
    }

    public function test_credit_decrements_balance_and_logs(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit'    => 1000,
            'current_balance' => 600,
        ]);

        $ledger = $this->service->credit($tenant, 300, 'invoice_paid', invoiceId: null);

        $this->assertSame(TenantCreditLedger::TYPE_CREDIT, $ledger->type);
        $this->assertEquals(300, (float) $ledger->balance_after);
        $this->assertEquals(300, (float) $tenant->fresh()->current_balance);
    }

    public function test_assert_can_charge_throws_when_insufficient(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit'    => 100,
            'current_balance' => 80,
        ]);

        $this->expectException(InsufficientCreditException::class);
        $this->service->assertCanCharge($tenant, 50);
    }
}
