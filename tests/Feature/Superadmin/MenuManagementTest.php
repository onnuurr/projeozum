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

    public function test_menus_index_page_renders(): void
    {
        // GET /superadmin/menus, Route::resource('superadmin') altındaki
        // superadmin/{superadmin} (show) tarafından gölgelenmemeli; gerçekte
        // MenuController@index → Superadmin::Menus sayfasını render etmeli.
        // component() ikinci argümanı false: Inertia test view-finder modül (::)
        // JS sayfa yollarıyla yapılandırılmadığından disk-varlık kontrolü atlanır;
        // asıl doğrulama yanıtın geçerli Inertia + doğru bileşen adı olmasıdır.
        $this->actingAs($this->superadmin)
            ->get('/superadmin/menus')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Superadmin::Menus', false));
    }

    public function test_superadmin_can_create_menu(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus', [
                'label'      => 'Katalog',
                'icon'       => 'package',
                'url'        => '/products',
                'sort_order' => 0,
                'is_active'  => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['label' => 'Katalog', 'icon' => 'package']);
    }

    public function test_superadmin_can_update_menu(): void
    {
        $menu = Menu::create(['label' => 'Eski', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->put("/superadmin/menus/{$menu->id}", [
                'label'     => 'Yeni',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['id' => $menu->id, 'label' => 'Yeni']);
    }

    public function test_deleting_menu_cascades_to_children(): void
    {
        $root  = Menu::create(['label' => 'Kök', 'sort_order' => 0]);
        $child = Menu::create(['parent_id' => $root->id, 'label' => 'Çocuk', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->delete("/superadmin/menus/{$root->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('superadmin_menus', ['id' => $root->id]);
        $this->assertDatabaseMissing('superadmin_menus', ['id' => $child->id]);
    }

    public function test_reorder_updates_sort_and_parent(): void
    {
        $a = Menu::create(['label' => 'A', 'sort_order' => 0]);
        $b = Menu::create(['label' => 'B', 'sort_order' => 1]);

        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus/reorder', [
                'items' => [
                    ['id' => $a->id, 'parent_id' => null, 'sort_order' => 1],
                    ['id' => $b->id, 'parent_id' => $a->id, 'sort_order' => 0],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['id' => $a->id, 'sort_order' => 1, 'parent_id' => null]);
        $this->assertDatabaseHas('superadmin_menus', ['id' => $b->id, 'sort_order' => 0, 'parent_id' => $a->id]);
    }

    public function test_reorder_rejects_cycle(): void
    {
        $parent = Menu::create(['label' => 'Parent', 'sort_order' => 0]);
        $child  = Menu::create(['parent_id' => $parent->id, 'label' => 'Child', 'sort_order' => 0]);

        // parent'ı kendi çocuğunun altına taşımak döngü yaratır → reddedilmeli
        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus/reorder', [
                'items' => [
                    ['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 0],
                ],
            ])
            ->assertSessionHasErrors('items');

        // Değişmemiş olmalı
        $this->assertDatabaseHas('superadmin_menus', ['id' => $parent->id, 'parent_id' => null]);
    }

    public function test_menus_index_exposes_route_catalog(): void
    {
        // Menüde olan route katalogdan gizlenir; olmayan menülenebilir route görünür.
        Menu::create(['label' => 'Ayarlar', 'route_name' => 'superadmin.settings', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/menus')
            ->assertInertia(fn ($page) => $page
                ->has('routes')
                ->where('routes', function ($routes) {
                    $names = collect($routes)->pluck('name');

                    return $names->contains('superadmin.menus')          // menüde yok → görünür
                        && ! $names->contains('superadmin.settings')      // menüde var → gizli
                        && ! $names->contains('superadmin.menus.update'); // parametreli → hariç
                })
            );
    }

    public function test_non_superadmin_cannot_manage_menus(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/superadmin/menus', ['label' => 'X', 'is_active' => true])
            ->assertForbidden();
    }
}
