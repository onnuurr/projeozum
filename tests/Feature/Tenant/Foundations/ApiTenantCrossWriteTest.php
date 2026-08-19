<?php

namespace Tests\Feature\Tenant\Foundations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Models\TenantPriceList;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ApiTenantOwnershipTest zaten invoices/settings için temel 403 kontrolünü yapıyor.
 * Bu test iki gerçek kullanıcı ile (userA / userB) yazma uçlarını (store/update/destroy)
 * ve price-lists uçlarını da kapsayarak EnforceTenantOwnership middleware'ini genişletir.
 */
class ApiTenantCrossWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private TenantInvoice $invoiceB;
    private TenantPriceList $priceListB;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo('tenant.manage');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userA->assignRole('tenant');

        $userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $userB->assignRole('tenant');

        $this->invoiceB = TenantInvoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'status'    => 'pending',
        ]);

        $this->priceListB = TenantPriceList::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    public function test_user_cannot_read_other_tenants_price_lists(): void
    {
        $this->actingAs($this->userA)
            ->getJson("/api/v1/tenants/{$this->tenantB->id}/price-lists")
            ->assertForbidden();
    }

    public function test_user_cannot_create_price_list_for_other_tenant(): void
    {
        $this->actingAs($this->userA)
            ->postJson("/api/v1/tenants/{$this->tenantB->id}/price-lists", [
                'discount_rate' => 10,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('tenant_price_lists', 1);
    }

    public function test_user_cannot_delete_other_tenants_price_list(): void
    {
        $this->actingAs($this->userA)
            ->deleteJson("/api/v1/tenants/{$this->tenantB->id}/price-lists/{$this->priceListB->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tenant_price_lists', ['id' => $this->priceListB->id]);
    }

    public function test_user_cannot_create_invoice_for_other_tenant(): void
    {
        $this->actingAs($this->userA)
            ->postJson("/api/v1/tenants/{$this->tenantB->id}/invoices", [
                'amount'   => 999.00,
                'currency' => 'TRY',
                'due_date' => now()->addMonth()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('tenant_invoices', 1);
    }

    public function test_user_cannot_mark_other_tenants_invoice_as_paid(): void
    {
        $this->actingAs($this->userA)
            ->patchJson("/api/v1/tenants/{$this->tenantB->id}/invoices/{$this->invoiceB->id}/mark-paid")
            ->assertForbidden();

        $this->assertNull($this->invoiceB->fresh()->paid_at);
    }

    public function test_user_cannot_read_other_tenants_settings(): void
    {
        $this->actingAs($this->userA)
            ->getJson("/api/v1/tenants/{$this->tenantB->id}/settings")
            ->assertForbidden();
    }

    public function test_user_cannot_update_other_tenants_settings(): void
    {
        $this->actingAs($this->userA)
            ->patchJson("/api/v1/tenants/{$this->tenantB->id}/settings", [
                'display_name' => 'Ele geçirilmiş isim',
            ])
            ->assertForbidden();
    }

    public function test_user_can_still_manage_own_tenant_price_lists(): void
    {
        $this->actingAs($this->userA)
            ->getJson("/api/v1/tenants/{$this->tenantA->id}/price-lists")
            ->assertOk();

        $this->actingAs($this->userA)
            ->postJson("/api/v1/tenants/{$this->tenantA->id}/price-lists", [
                'discount_rate' => 15,
            ])
            ->assertCreated();
    }
}
