<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);
        $role->givePermissionTo('tenant.manage');
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_invoice_can_be_created(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/invoices", [
                'amount'   => 5000.00,
                'currency' => 'TRY',
                'due_date' => '2026-06-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_invoice_can_be_marked_as_paid(): void
    {
        $invoice = TenantInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$this->tenant->id}/invoices/{$invoice->id}/mark-paid");

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_invoices_can_be_listed(): void
    {
        TenantInvoice::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/invoices");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
