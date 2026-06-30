<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Creative modülünün hibrit granüler yetki kapılarını doğrular:
 *   creative.view (okuma) ile creative.template.manage (yazma) birbirinden bağımsızdır,
 *   ve superadmin Gate::before ile açık izin olmadan hepsini geçer.
 */
class CreativePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function permission(string $name): void
    {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_plain_user_is_forbidden_from_studio(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/creative/studio')->assertForbidden();
    }

    public function test_view_permission_allows_reading_templates(): void
    {
        $this->permission('creative.view');
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        $this->actingAs($user)->get('/creative/templates')->assertOk();
    }

    public function test_view_permission_cannot_manage_templates(): void
    {
        $this->permission('creative.view');
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        // Yazma için ayrı izin gerekir → yetki kapısı reddeder (validasyondan önce).
        $this->actingAs($user)->post('/creative/templates', [])->assertForbidden();
    }

    public function test_template_manage_permission_passes_authorization(): void
    {
        $this->permission('creative.template.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('creative.template.manage');

        // Yetki geçer; boş gövde validasyona takılır (302/422) ama 403 DEĞİLDİR.
        $response = $this->actingAs($user)->post('/creative/templates', []);
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_superadmin_bypasses_via_gate_before(): void
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin)->get('/creative/templates')->assertOk();
    }
}
