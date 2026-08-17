<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Superadmin\Jobs\RunArchitectureDoctorFixJob;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ArchitectureDoctorFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'architecture-doctor.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'architecture-doctor.manage', 'guard_name' => 'web']);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_fix_requires_manage_permission(): void
    {
        $user = $this->userWithPermissions(['architecture-doctor.view']);

        $this->actingAs($user)
            ->post('/superadmin/architecture-doctor/fix')
            ->assertForbidden();
    }

    public function test_manage_user_can_queue_a_fix_run(): void
    {
        config(['superadmin.architecture_doctor.enabled' => true]);
        Queue::fake();

        $user = $this->userWithPermissions(['architecture-doctor.manage']);

        $this->actingAs($user)
            ->post('/superadmin/architecture-doctor/fix')
            ->assertRedirect();

        $this->assertDatabaseHas('architecture_doctor_fix_runs', [
            'status' => ArchitectureDoctorFixRun::STATUS_QUEUED,
            'triggered_by_user_id' => $user->id,
        ]);

        Queue::assertPushed(RunArchitectureDoctorFixJob::class);
    }

    public function test_fix_is_a_no_op_when_feature_disabled(): void
    {
        config(['superadmin.architecture_doctor.enabled' => false]);
        Queue::fake();

        $user = $this->userWithPermissions(['architecture-doctor.manage']);

        $this->actingAs($user)
            ->post('/superadmin/architecture-doctor/fix')
            ->assertRedirect();

        $this->assertDatabaseCount('architecture_doctor_fix_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_second_fix_call_does_not_dispatch_while_one_is_active(): void
    {
        config(['superadmin.architecture_doctor.enabled' => true]);
        Queue::fake();

        $user = $this->userWithPermissions(['architecture-doctor.manage']);

        ArchitectureDoctorFixRun::create(['status' => ArchitectureDoctorFixRun::STATUS_RUNNING]);

        $this->actingAs($user)
            ->post('/superadmin/architecture-doctor/fix')
            ->assertRedirect();

        $this->assertDatabaseCount('architecture_doctor_fix_runs', 1);
        Queue::assertNothingPushed();
    }

    public function test_scan_only_requires_view_permission_and_refreshes_the_report(): void
    {
        $user = $this->userWithPermissions(['architecture-doctor.view']);

        $this->actingAs($user)
            ->post('/superadmin/architecture-doctor/scan')
            ->assertRedirect();

        $this->assertFileExists(storage_path('app/architecture-doctor.json'));
    }
}
