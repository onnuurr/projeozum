<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CreativeMannequins.vue'da "Yeniden üret"/"Sil" butonları useCan('creative.asset.manage')
 * ile gizleniyor; bu kararı frontend'e taşıyan tek kaynak Inertia'nın paylaştığı
 * auth.permissions dizisidir. Bu test o dizinin doğru dolduğunu doğrular.
 */
class CreativeMannequinsActionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Modül Inertia sayfaları test view-finder'ında çözülmez; varlık kontrolünü kapat
        // (bkz. CreativeReviewWorkflowTest::setUp).
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function permission(string $name): void
    {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_user_without_asset_manage_permission_does_not_receive_it_in_auth_permissions(): void
    {
        $this->permission('creative.view');
        $this->permission('creative.asset.manage');

        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        $this->actingAs($user)
            ->get('/creative/mannequins')
            ->assertInertia(fn ($page) => $page
                ->component('Creative::CreativeMannequins')
                ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('creative.asset.manage')));
    }

    public function test_user_with_asset_manage_permission_receives_it_in_auth_permissions(): void
    {
        $this->permission('creative.view');
        $this->permission('creative.asset.manage');

        $user = User::factory()->create();
        $user->givePermissionTo(['creative.view', 'creative.asset.manage']);

        $this->actingAs($user)
            ->get('/creative/mannequins')
            ->assertInertia(fn ($page) => $page
                ->component('Creative::CreativeMannequins')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('creative.asset.manage')));
    }
}
