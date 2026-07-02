<?php
// tests/Feature/Tenant/Portal/TenantUserServiceTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantUserService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        foreach (['portal.access', 'portal.orders.view', 'portal.checkout', 'portal.users.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])
            ->givePermissionTo('portal.access');
    }

    public function test_create_assigns_role_and_only_selected_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);

        $user = $service->create($tenant, [
            'name' => 'Alt Kullanıcı',
            'email' => 'alt@example.test',
            'password' => 'sifre1234',
            'permissions' => ['portal.orders.view', 'portal.users.manage'], // ikincisi katalog dışı
        ]);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('tenant-user'));
        $this->assertTrue($user->can('portal.access'));      // rolden
        $this->assertTrue($user->can('portal.orders.view')); // doğrudan
        $this->assertFalse($user->can('portal.users.manage')); // katalog dışı → atlandı
    }

    public function test_update_resyncs_direct_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);
        $user = $service->create($tenant, [
            'name' => 'A', 'email' => 'b@example.test', 'password' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ]);

        $service->update($user, ['name' => 'A2', 'permissions' => ['portal.checkout']]);
        $user = $user->fresh();

        $this->assertSame('A2', $user->name);
        $this->assertFalse($user->can('portal.orders.view'));
        $this->assertTrue($user->can('portal.checkout'));
        $this->assertTrue($user->can('portal.access')); // baseline korunur
    }

    public function test_toggle_active_and_delete(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);
        $user = $service->create($tenant, [
            'name' => 'A', 'email' => 'c@example.test', 'password' => 'sifre1234', 'permissions' => [],
        ]);

        $service->toggleActive($user);
        $this->assertFalse($user->fresh()->is_active);

        $service->delete($user);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
