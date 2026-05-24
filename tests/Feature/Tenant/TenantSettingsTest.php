<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
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

    public function test_valid_key_can_be_updated(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$this->tenant->id}/settings", [
                'currency' => 'USD',
                'language' => 'en',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.language', 'en');
    }

    public function test_unknown_key_returns_422(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$this->tenant->id}/settings", [
                'unknown_key' => 'value',
            ]);

        $response->assertUnprocessable();
    }

    public function test_merge_preserves_other_keys(): void
    {
        $this->tenant->update(['settings' => ['currency' => 'TRY', 'language' => 'tr']]);

        $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$this->tenant->id}/settings", [
                'currency' => 'EUR',
            ]);

        $tenant = $this->tenant->fresh();
        $this->assertEquals('EUR', $tenant->settings['currency']);
        $this->assertEquals('tr', $tenant->settings['language']);
    }
}
