<?php

namespace Tests\Feature\PatternEngine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\Models\PatternGenerationJob;
use Modules\PatternEngine\Services\Drivers\MockPatternEngineClient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternEnginePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PatternEngineClient::class, MockPatternEngineClient::class);
    }

    private function permission(string $name): void
    {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_plain_user_is_forbidden(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/pattern-engine')->assertForbidden();
    }

    public function test_permission_holder_can_view_and_generate(): void
    {
        $this->permission('patternengine.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.manage');

        // Tam sayfa render Vite manifest'e ihtiyaç duyar (bkz. Inertia versiyon kontrolü);
        // burada asıl doğrulanan yetki kapısı — 403 DEĞİLDİR yeterli (bkz. CreativePermissionTest,
        // aynı desen).
        $response = $this->actingAs($user)->get('/pattern-engine');
        $this->assertNotSame(403, $response->getStatusCode());

        $response = $this->actingAs($user)->post('/pattern-engine/generate', [
            'garment' => 'tshirt',
            'size'    => '8Y',
        ]);

        $response->assertRedirect(route('patternengine.index'));
    }

    public function test_grade_sizes_field_routes_to_graded_queue(): void
    {
        $this->permission('patternengine.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.manage');

        $response = $this->actingAs($user)->post('/pattern-engine/generate', [
            'garment'     => 'tshirt',
            'size'        => '8Y',
            'grade_sizes' => ['4Y', '12Y'],
        ]);

        $response->assertRedirect(route('patternengine.index'));

        $job = PatternGenerationJob::latest()->first();
        $this->assertTrue($job->isGraded());
        $this->assertSame(['4Y', '12Y'], $job->graded_sizes);
    }

    public function test_grade_sizes_cannot_repeat_the_base_size(): void
    {
        $this->permission('patternengine.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.manage');

        $response = $this->actingAs($user)->post('/pattern-engine/generate', [
            'garment'     => 'tshirt',
            'size'        => '8Y',
            'grade_sizes' => ['8Y'],
        ]);

        $response->assertSessionHasErrors('grade_sizes.0');
    }
}
