<?php
// tests/Feature/Tenant/Portal/PortalUserMassAssignmentTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * StorePortalUserRequest sadece name/email/password/permissions'ı validated() eder;
 * TenantUserService::create() tenant_id ve rolü elle sabitler (bkz. TenantUserService.php).
 * Bu test o çift korumayı kilitler: bir tenant admin'i, alt kullanıcı oluşturma
 * formuna normalde kabul edilmeyen alanlar (tenant_id, id, role, is_active) ekleyip
 * kendi tenant'ının dışına kullanıcı taşımayı ya da ayrıcalık yükseltmeyi dener.
 */
class PortalUserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        $perms = ['portal.access', 'portal.users.manage', 'portal.orders.view'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])->givePermissionTo($perms);
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])->givePermissionTo('portal.access');
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

    public function test_foreign_tenant_id_and_role_fields_in_payload_do_not_escalate(): void
    {
        $this->bootRbac();
        $ownTenant = Tenant::factory()->create(['slug' => 'ma-own-' . uniqid()]);
        $foreignTenant = Tenant::factory()->create(['slug' => 'ma-foreign-' . uniqid()]);
        $this->actingAs($this->admin($ownTenant));

        $this->post($this->base($ownTenant) . '/users', [
            'name'                  => 'Alt',
            'email'                 => 'ma-alt@pm.test',
            'password'              => 'sifre1234',
            'password_confirmation' => 'sifre1234',
            'permissions'           => ['portal.orders.view'],
            // Mass assignment denemesi:
            'tenant_id'             => $foreignTenant->id,
            'id'                    => 999999,
            'role'                  => 'tenant',
            'is_active'             => false,
        ])->assertRedirect();

        $sub = User::where('email', 'ma-alt@pm.test')->firstOrFail();

        $this->assertSame($ownTenant->id, $sub->tenant_id);
        $this->assertNotSame($foreignTenant->id, $sub->tenant_id);
        $this->assertNotEquals(999999, $sub->id);
        $this->assertTrue($sub->hasRole('tenant-user'));
        $this->assertFalse($sub->hasRole('tenant'));
        $this->assertTrue($sub->is_active);
    }

    public function test_partial_privilege_escalation_in_mixed_permissions_array_is_filtered(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'ma-mix-' . uniqid()]);
        $this->actingAs($this->admin($tenant));

        // 'portal.users.manage' katalog dışı — validasyon tüm isteği reddeder (all-or-nothing).
        $response = $this->post($this->base($tenant) . '/users', [
            'name'                  => 'Alt',
            'email'                 => 'ma-mix@pm.test',
            'password'              => 'sifre1234',
            'password_confirmation' => 'sifre1234',
            'permissions'           => ['portal.orders.view', 'portal.users.manage'],
        ]);

        $response->assertSessionHasErrors();
        $this->assertNull(User::where('email', 'ma-mix@pm.test')->first());
    }
}
