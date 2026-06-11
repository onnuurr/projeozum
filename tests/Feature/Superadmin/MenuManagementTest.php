<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);
    }

    public function test_menu_tree_is_shared_with_inertia_responses(): void
    {
        Menu::create(['label' => 'Pano', 'icon' => 'dashboard', 'url' => '/tenant/dashboard', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/settings')
            ->assertInertia(fn ($page) => $page
                ->where('menu.0.label', 'Pano')
                ->where('menu.0.icon', 'dashboard')
                ->where('menu.0.to', '/tenant/dashboard')
            );
    }
}
