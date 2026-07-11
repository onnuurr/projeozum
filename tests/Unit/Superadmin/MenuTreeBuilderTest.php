<?php

namespace Tests\Unit\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\Models\Menu;
use Modules\Superadmin\Services\MenuTreeBuilder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuTreeBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_nested_tree_ordered(): void
    {
        $root = Menu::create(['label' => 'Katalog', 'icon' => 'package', 'sort_order' => 0]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Ürünler', 'url' => '/products', 'sort_order' => 1]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Kategoriler', 'url' => '/categories', 'sort_order' => 0]);

        $tree = MenuTreeBuilder::forUser(null);

        $this->assertCount(1, $tree);
        $this->assertSame('Katalog', $tree[0]['label']);
        $this->assertSame('package', $tree[0]['icon']);
        $this->assertCount(2, $tree[0]['children']);
        // sort_order'a göre: Kategoriler (0) önce, Ürünler (1) sonra
        $this->assertSame('Kategoriler', $tree[0]['children'][0]['label']);
        $this->assertSame('Ürünler', $tree[0]['children'][1]['label']);
        $this->assertSame('/products', $tree[0]['children'][1]['to']);
    }

    public function test_hides_menu_without_permission_and_drops_subtree(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $root = Menu::create(['label' => 'Atölye', 'permission' => 'atelier.manage', 'sort_order' => 0]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Modeller', 'url' => '/atelier', 'sort_order' => 0]);

        $user = User::factory()->create(); // izni yok

        $tree = MenuTreeBuilder::forUser($user);

        $this->assertCount(0, $tree); // parent gizli → alt ağaç da düşer
    }

    public function test_shows_permitted_menu_to_user_with_permission(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        Menu::create(['label' => 'Atölye', 'permission' => 'atelier.manage', 'sort_order' => 0]);

        $user = User::factory()->create();
        $user->givePermissionTo('atelier.manage');

        $tree = MenuTreeBuilder::forUser($user);

        $this->assertCount(1, $tree);
        $this->assertSame('Atölye', $tree[0]['label']);
    }

    public function test_excludes_inactive_menus(): void
    {
        Menu::create(['label' => 'Gizli', 'is_active' => false, 'sort_order' => 0]);

        $this->assertCount(0, MenuTreeBuilder::forUser(null));
    }

    /**
     * Regresyon: 'portal.marketplace.index' gibi domain'i {slug} bekleyen bir route
     * menüye eklenmişse, route(..., [], false) UrlGenerationException fırlatır. Bu
     * prop HER Inertia sayfasında paylaşıldığı için tek bozuk kayıt tüm uygulamayı
     * kırardı — resolveTo() artık bunu yakalayıp url'e (varsa) düşer.
     */
    public function test_domain_scoped_route_does_not_crash_menu_resolution(): void
    {
        Menu::create([
            'label'      => 'Portal Marketplace',
            'route_name' => 'portal.marketplace.index',
            'url'        => '/marketplace-fallback',
            'sort_order' => 0,
        ]);

        $tree = MenuTreeBuilder::forUser(null);

        $this->assertCount(1, $tree);
        $this->assertSame('/marketplace-fallback', $tree[0]['to']);
    }
}
