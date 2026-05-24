<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantPriceList;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantPriceListTest extends TestCase
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

    public function test_price_list_can_be_created(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/price-lists", [
                'discount_rate' => 15.5,
                'valid_from'    => '2026-01-01',
                'valid_until'   => '2026-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.discount_rate', 15.5);
    }

    public function test_price_list_can_be_listed(): void
    {
        TenantPriceList::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/price-lists");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_price_list_hard_delete(): void
    {
        $priceList = TenantPriceList::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->superadmin)
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/price-lists/{$priceList->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tenant_price_lists', ['id' => $priceList->id]);
    }
}
