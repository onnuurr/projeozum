<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Creative\Models\Pose;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Mokap ayarları kayıt uçları flash basmaz: sekmeler kendi toast'unu gösteriyor (çift toast oluyordu). */
class MockupSettingsNoFlashTest extends TestCase
{
    use RefreshDatabase;

    public function test_pose_tag_save_redirects_without_flash(): void
    {
        foreach (['creative-settings.view', 'mockup-settings.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['creative-settings.view', 'mockup-settings.manage']);
        $pose = Pose::create(['pose_key' => 'step_forward', 'label' => 'Adım', 'prompt' => 'p', 'status' => Pose::STATUS_READY]);

        $this->actingAs($user)->put("/creative/settings/mockup/poses/{$pose->id}", ['mockup_regions' => ['bottom'], 'requires' => [], 'view' => 'front'])
            ->assertRedirect()->assertSessionMissing('success');
        $this->assertSame(['bottom'], $pose->fresh()->mockup_regions);
    }
}
