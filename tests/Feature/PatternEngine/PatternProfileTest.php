<?php

namespace Tests\Feature\PatternEngine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\ProvenanceDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\Exceptions\ProfileProtectedException;
use Modules\PatternEngine\Services\Drivers\MockPatternEngineClient;
use Modules\PatternEngine\Services\PatternEngineService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PatternEngineClient::class, MockPatternEngineClient::class);
    }

    private function authorizedUser(): User
    {
        Permission::firstOrCreate(['name' => 'patternengine.profile.manage', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.profile.manage');

        return $user;
    }

    public function test_plain_user_cannot_view_profiles(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/pattern-engine/profiles')->assertForbidden();
    }

    public function test_patternengine_manage_alone_cannot_view_profiles(): void
    {
        // Üretim/gradasyon yetkisi profil yönetimi yetkisini KAPSAMAZ — bilinçli ayrım.
        Permission::firstOrCreate(['name' => 'patternengine.manage', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.manage');

        $this->actingAs($user)->get('/pattern-engine/profiles')->assertForbidden();
    }

    public function test_permission_holder_can_view_profiles_index(): void
    {
        $response = $this->actingAs($this->authorizedUser())->get('/pattern-engine/profiles');

        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_store_creates_new_profile(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/profiles', [
            'name'         => 'musteri_test',
            'base_profile' => 'ts_default',
            'sizes'        => [
                '6Y' => [
                    'height' => 1160, 'chest_circ' => 620, 'waist_circ' => 570,
                    'shoulder_width' => 285, 'neck_circ' => 280, 'back_width' => 270,
                    'arm_length' => 385, 'armhole_depth' => 142, 'upper_arm_circ' => 215,
                    'body_length' => 440,
                ],
            ],
            'provenance' => [
                'source'               => 'musteri_test_modelist',
                'verified_by_modelist' => true,
            ],
        ]);

        $response->assertRedirect(route('patternengine.profiles.index'));
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_store_rejects_ts_default_name(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/profiles', [
            'name'       => 'ts_default',
            'sizes'      => ['6Y' => ['chest_circ' => 600]],
            'provenance' => ['source' => 'x'],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_rejects_unsafe_name(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/profiles', [
            'name'       => '../evil',
            'sizes'      => ['6Y' => ['chest_circ' => 600]],
            'provenance' => ['source' => 'x'],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_at_least_one_size(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/profiles', [
            'name'       => 'musteri_empty',
            'sizes'      => [],
            'provenance' => ['source' => 'x'],
        ]);

        $response->assertSessionHasErrors('sizes');
    }

    public function test_service_lists_and_fetches_profiles_via_mock(): void
    {
        $service = app(PatternEngineService::class);

        $summaries = $service->listProfiles();
        $this->assertNotEmpty($summaries);
        $this->assertSame('ts_default', $summaries[0]->name);

        $detail = $service->getProfile('ts_default');
        $this->assertNotNull($detail);
        $this->assertSame('ts_default', $detail->name);

        $this->assertNull($service->getProfile('bilinmeyen_profil'));
    }

    public function test_saving_ts_default_via_service_throws(): void
    {
        $service = app(PatternEngineService::class);

        $this->expectException(ProfileProtectedException::class);
        $service->saveProfile(new SaveProfileRequestDTO(
            name: 'ts_default',
            sizes: ['8Y' => ['chest_circ' => 670]],
            provenance: new ProvenanceDTO(source: 'x'),
        ));
    }
}
