<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use Modules\Bagisto\Jobs\DeleteTenantFromBagisto;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Bagisto\Jobs\RenameTenantInBagisto;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 3: Tenant modülünün domain event'leri (TenantActivated/TenantDeactivated)
 * gerçekten fırlatıldığında, Bagisto\Providers\EventServiceProvider'daki
 * bağlantının doğru job'u doğru argümanlarla kuyruğa attığını doğrular.
 */
class TenantSyncDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        // QUEUE_CONNECTION=sync olduğu için (phpunit.xml) fake edilmeden
        // yapılan HERHANGİ bir dispatch (bu dosyadaki bazı testlerin fixture
        // kurulumu için çağırdığı create() dahil) job'u senkron çalıştırıp
        // gerçek bir HTTP isteğiyle bagisto.sync.base_url'e çıkardı — 422
        // dönüyordu. Tüm testler için baştan fake edilir.
        Queue::fake();
    }

    public function test_create_with_owner_dispatches_activated_push_with_plain_password(): void
    {
        $tenant = app(TenantService::class)->create([
            'code' => 'ACME1',
            'name' => 'Acme',
            'owner_name' => 'Ali Veli',
            'owner_email' => 'ali@example.com',
            'owner_password' => 'secret123',
        ]);

        Queue::assertPushed(PushTenantToBagisto::class, function ($job) use ($tenant) {
            return $this->jobProperty($job, 'tenantId') === $tenant->id
                && $this->jobProperty($job, 'event') === 'activated'
                && $this->jobProperty($job, 'plainPassword') === 'secret123';
        });
    }

    public function test_create_without_owner_does_not_dispatch(): void
    {
        app(TenantService::class)->create([
            'code' => 'ACME2',
            'name' => 'Acme 2',
        ]);

        Queue::assertNotPushed(PushTenantToBagisto::class);
    }

    public function test_update_dispatches_activated_when_still_active(): void
    {
        $tenant = app(TenantService::class)->create([
            'code' => 'ACME3',
            'name' => 'Acme 3',
            'owner_name' => 'Ali Veli',
            'owner_email' => 'ali3@example.com',
            'owner_password' => 'secret123',
        ]);

        app(TenantService::class)->update($tenant, ['name' => 'Acme 3 Updated']);

        Queue::assertPushed(PushTenantToBagisto::class, function ($job) use ($tenant) {
            return $this->jobProperty($job, 'tenantId') === $tenant->id
                && $this->jobProperty($job, 'event') === 'activated'
                && $this->jobProperty($job, 'plainPassword') === null;
        });
    }

    public function test_suspend_dispatches_deactivated_push(): void
    {
        $tenant = app(TenantService::class)->create([
            'code' => 'ACME4',
            'name' => 'Acme 4',
            'owner_name' => 'Ali Veli',
            'owner_email' => 'ali4@example.com',
            'owner_password' => 'secret123',
        ]);

        app(TenantService::class)->suspend($tenant);

        Queue::assertPushed(PushTenantToBagisto::class, function ($job) use ($tenant) {
            return $this->jobProperty($job, 'tenantId') === $tenant->id
                && $this->jobProperty($job, 'event') === 'deactivated';
        });
    }

    public function test_activate_dispatches_activated_push_without_password(): void
    {
        $tenant = app(TenantService::class)->create([
            'code' => 'ACME5',
            'name' => 'Acme 5',
            'owner_name' => 'Ali Veli',
            'owner_email' => 'ali5@example.com',
            'owner_password' => 'secret123',
        ]);
        app(TenantService::class)->suspend($tenant);

        app(TenantService::class)->activate($tenant->fresh());

        Queue::assertPushed(PushTenantToBagisto::class, function ($job) use ($tenant) {
            return $this->jobProperty($job, 'tenantId') === $tenant->id
                && $this->jobProperty($job, 'event') === 'activated'
                && $this->jobProperty($job, 'plainPassword') === null;
        });
    }

    public function test_delete_dispatches_bagisto_deletion_with_soft_deleted_owner_email(): void
    {
        $tenant = app(TenantService::class)->create([
            'code' => 'ACME6',
            'name' => 'Acme 6',
            'owner_name' => 'Ali Veli',
            'owner_email' => 'ali6@example.com',
            'owner_password' => 'secret123',
        ]);

        // Silme guard'ı bağlı kullanıcı istemez — owner önce silinir (soft delete).
        User::where('tenant_id', $tenant->id)->get()->each->delete();

        app(TenantService::class)->delete($tenant->fresh());

        $this->assertSoftDeleted($tenant);
        Queue::assertPushed(DeleteTenantFromBagisto::class, function ($job) {
            return $this->jobProperty($job, 'email') === 'ali6@example.com';
        });
    }

    public function test_owner_moved_to_another_tenant_removes_bagisto_account_once(): void
    {
        $tenant = $this->tenantWithOwner('ACME7', 'ali7@example.com');
        $other = Tenant::factory()->create();

        User::where('tenant_id', $tenant->id)->first()->update(['tenant_id' => $other->id]);
        // Owner zaten ayrıldı; tenant silinirken aynı hesabı ikinci kez hedeflememeli.
        app(TenantService::class)->delete($tenant->fresh());

        Queue::assertPushed(DeleteTenantFromBagisto::class, 1);
        Queue::assertPushed(DeleteTenantFromBagisto::class, fn ($job) => $this->jobProperty($job, 'email') === 'ali7@example.com');
    }

    public function test_owner_deleted_removes_bagisto_account(): void
    {
        $tenant = $this->tenantWithOwner('ACME8', 'ali8@example.com');

        User::where('tenant_id', $tenant->id)->first()->delete();

        Queue::assertPushed(DeleteTenantFromBagisto::class, fn ($job) => $this->jobProperty($job, 'email') === 'ali8@example.com');
    }

    public function test_non_owner_user_deleted_or_moved_does_not_touch_bagisto(): void
    {
        $tenant = $this->tenantWithOwner('ACME9', 'ali9@example.com');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff2 = User::factory()->create(['tenant_id' => $tenant->id]);

        $staff->delete();
        $staff2->update(['tenant_id' => null]);

        Queue::assertNotPushed(DeleteTenantFromBagisto::class);
    }

    public function test_owner_profile_update_is_not_treated_as_detach(): void
    {
        $tenant = $this->tenantWithOwner('ACME10', 'ali10@example.com');

        User::where('tenant_id', $tenant->id)->first()->update(['name' => 'Yeni Ad']);

        Queue::assertNotPushed(DeleteTenantFromBagisto::class);
    }

    public function test_owner_email_change_via_tenant_form_renames_bagisto_account(): void
    {
        $tenant = $this->tenantWithOwner('ACME11', 'eski@example.com');

        app(TenantService::class)->update($tenant, ['owner_email' => 'yeni@example.com']);

        Queue::assertPushed(RenameTenantInBagisto::class, fn ($job) => $this->jobProperty($job, 'oldEmail') === 'eski@example.com'
            && $this->jobProperty($job, 'newEmail') === 'yeni@example.com');
        Queue::assertNotPushed(DeleteTenantFromBagisto::class);
    }

    public function test_non_owner_email_change_does_not_rename(): void
    {
        $tenant = $this->tenantWithOwner('ACME12', 'ali12@example.com');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $staff->update(['email' => 'personel-yeni@example.com']);

        Queue::assertNotPushed(RenameTenantInBagisto::class);
    }

    public function test_owner_moved_with_new_email_removes_old_account_instead_of_renaming(): void
    {
        $tenant = $this->tenantWithOwner('ACME13', 'ali13@example.com');

        User::where('tenant_id', $tenant->id)->first()->update(['tenant_id' => null, 'email' => 'baska@example.com']);

        Queue::assertNotPushed(RenameTenantInBagisto::class);
        Queue::assertPushed(DeleteTenantFromBagisto::class, fn ($job) => $this->jobProperty($job, 'email') === 'ali13@example.com');
    }

    private function tenantWithOwner(string $code, string $email): Tenant
    {
        return app(TenantService::class)->create([
            'code' => $code,
            'name' => $code,
            'owner_name' => 'Ali Veli',
            'owner_email' => $email,
            'owner_password' => 'secret123',
        ]);
    }

    private function jobProperty(object $job, string $name): mixed
    {
        $property = new \ReflectionProperty($job, $name);

        return $property->getValue($job);
    }
}
