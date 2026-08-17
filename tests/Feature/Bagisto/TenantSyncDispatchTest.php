<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
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
    }

    public function test_create_with_owner_dispatches_activated_push_with_plain_password(): void
    {
        Queue::fake();

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
        Queue::fake();

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

        Queue::fake();

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

        Queue::fake();

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

        Queue::fake();

        app(TenantService::class)->activate($tenant->fresh());

        Queue::assertPushed(PushTenantToBagisto::class, function ($job) use ($tenant) {
            return $this->jobProperty($job, 'tenantId') === $tenant->id
                && $this->jobProperty($job, 'event') === 'activated'
                && $this->jobProperty($job, 'plainPassword') === null;
        });
    }

    private function jobProperty(object $job, string $name): mixed
    {
        $property = new \ReflectionProperty($job, $name);

        return $property->getValue($job);
    }
}
