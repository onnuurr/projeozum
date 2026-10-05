<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 403 yanıtları bootstrap/app.php'deki respond() ile Errors/Forbidden Inertia sayfasına çevrilir;
 * JSON isteyen istemciler ise eski JSON 403'ü almaya devam eder.
 */
class ForbiddenPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'forbidden-test.view', 'guard_name' => 'web']);

        Route::middleware(['web', 'auth', 'permission:forbidden-test.view'])
            ->get('/__forbidden-test', fn () => 'ok');
    }

    public function test_inertia_request_without_permission_renders_forbidden_page(): void
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->get('/__forbidden-test')
            ->assertForbidden()
            ->assertJsonPath('component', 'Errors/Forbidden');
    }

    public function test_json_request_keeps_plain_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/__forbidden-test')
            ->assertForbidden()
            ->assertJsonMissingPath('component');
    }

    public function test_user_with_permission_passes(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('forbidden-test.view');

        $this->actingAs($user)->get('/__forbidden-test')->assertOk();
    }
}
