<?php

namespace Tests\Feature\Superadmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\database\seeders\MenuSeeder;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_root_menus_with_children(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);

        (new MenuSeeder())->run();

        // 10 kök menü
        $this->assertSame(10, Menu::roots()->count());

        // Pano kökü ikonlu ve child'lı
        $pano = Menu::where('label', 'Pano')->whereNull('parent_id')->first();
        $this->assertNotNull($pano);
        $this->assertSame('dashboard', $pano->icon);
        $this->assertGreaterThan(0, $pano->children()->count());

        // Atölye izne bağlı
        $atelier = Menu::where('label', 'Atölye')->whereNull('parent_id')->first();
        $this->assertSame('atelier.manage', $atelier->permission);
    }

    public function test_seeder_is_idempotent(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);

        (new MenuSeeder())->run();
        (new MenuSeeder())->run();

        $this->assertSame(10, Menu::roots()->count());
    }
}
