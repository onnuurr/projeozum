<?php
// tests/Feature/Tenant/Portal/PortalUserManagementTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        $perms = [
            'portal.access', 'portal.users.manage', 'portal.orders.view',
            'portal.checkout', 'portal.invoices.view',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])->givePermissionTo($perms);
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])->givePermissionTo('portal.access');
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])->givePermissionTo($perms);
    }

    private function admin(Tenant $tenant): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant->id]);
        $u->assignRole('tenant');
        return $u;
    }

    private function base(Tenant $t): string
    {
        return 'http://' . $t->slug . '.bizimsite.test';
    }

    public function test_admin_creates_sub_user_with_selected_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pm-' . uniqid()]);
        $this->actingAs($this->admin($tenant));

        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ])->assertRedirect();

        $sub = User::where('email', 'alt@pm.test')->first();
        $this->assertNotNull($sub);
        $this->assertSame($tenant->id, $sub->tenant_id);
        $this->assertTrue($sub->hasRole('tenant-user'));
        $this->assertTrue($sub->can('portal.orders.view'));
        $this->assertFalse($sub->can('portal.checkout'));
    }

    public function test_sub_user_cannot_access_unselected_route_but_can_selected(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pm-' . uniqid()]);
        $admin = $this->admin($tenant);
        $this->actingAs($admin);
        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt2@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ]);
        $sub = User::where('email', 'alt2@pm.test')->first();

        $this->actingAs($sub);
        $this->get($this->base($tenant) . '/orders')->assertOk();      // seçili
        $this->get($this->base($tenant) . '/checkout')->assertForbidden(); // seçilmemiş
        $this->get($this->base($tenant) . '/users')->assertForbidden();    // yönetim yok
    }

    public function test_admin_cannot_manage_cross_tenant_user(): void
    {
        $this->bootRbac();
        $tenantA = Tenant::factory()->create(['slug' => 'pa-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'pb-' . uniqid()]);
        $adminA = $this->admin($tenantA);

        $subB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $subB->assignRole('tenant-user');

        $this->actingAs($adminA);
        // adminA, kendi subdomain'inden tenantB kullanıcısını silmeye çalışır
        $this->delete($this->base($tenantA) . '/users/' . $subB->id)->assertForbidden();
    }

    public function test_admin_cannot_delete_self_or_other_admin(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'ps-' . uniqid()]);
        $admin = $this->admin($tenant);
        $otherAdmin = $this->admin($tenant);

        $this->actingAs($admin);
        $this->delete($this->base($tenant) . '/users/' . $admin->id)->assertForbidden();
        $this->delete($this->base($tenant) . '/users/' . $otherAdmin->id)->assertForbidden();
    }

    public function test_reject_admin_permission_grant_to_sub_user(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pr-' . uniqid()]);
        $this->actingAs($this->admin($tenant));

        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt3@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.users.manage'], // katalog dışı
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_toggle_active_reset_password_and_delete(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pt-' . uniqid()]);
        $admin = $this->admin($tenant);
        $this->actingAs($admin);
        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt4@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234', 'permissions' => [],
        ]);
        $sub = User::where('email', 'alt4@pm.test')->first();

        $this->post($this->base($tenant) . '/users/' . $sub->id . '/toggle-active')->assertRedirect();
        $this->assertFalse($sub->fresh()->is_active);

        $this->post($this->base($tenant) . '/users/' . $sub->id . '/reset-password', [
            'password' => 'yenisifre99', 'password_confirmation' => 'yenisifre99',
        ])->assertRedirect();

        $this->delete($this->base($tenant) . '/users/' . $sub->id)->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $sub->id]);
    }

    public function test_deactivated_sub_user_is_blocked_on_portal(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pd-' . uniqid()]);
        $admin = $this->admin($tenant);
        $this->actingAs($admin);
        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'altdeact@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ]);
        $sub = \App\Models\User::where('email', 'altdeact@pm.test')->first();
        $sub->update(['is_active' => false]);

        $this->actingAs($sub);
        $this->get($this->base($tenant) . '/orders')->assertForbidden();
    }
}
