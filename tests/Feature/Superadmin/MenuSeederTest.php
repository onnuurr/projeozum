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
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);

        (new MenuSeeder())->run();

        // 10 standart tenant kökü + 1 superadmin kökü (Sistem Logları) = 11
        $this->assertSame(11, Menu::roots()->count());

        // Pano kökü ikonlu ve child'lı
        $pano = Menu::where('label', 'Pano')->whereNull('parent_id')->first();
        $this->assertNotNull($pano);
        $this->assertSame('dashboard', $pano->icon);
        $this->assertGreaterThan(0, $pano->children()->count());

        // Atölye izne bağlı (okuma izni menü görünürlüğünü yönetir)
        $atelier = Menu::where('label', 'Atölye')->whereNull('parent_id')->first();
        $this->assertSame('atelier.view', $atelier->permission);

        // Görünürlük = erişilebilirlik: kökteki gerçek route'un izniyle eşleşmeli
        $iliskiler = Menu::where('label', 'İlişkiler')->whereNull('parent_id')->first();
        $this->assertSame('tenant.view', $iliskiler->permission);

        $stok = Menu::where('label', 'Stok')->whereNull('parent_id')->first();
        $this->assertSame('stock.manage', $stok->permission);
    }

    public function test_seeder_is_idempotent(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);

        (new MenuSeeder())->run();
        (new MenuSeeder())->run();

        $this->assertSame(11, Menu::roots()->count());
    }

    public function test_reseed_reconciles_permission_drift_on_existing_roots(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);

        (new MenuSeeder())->run();

        // Kök zaten var olduğu için firstOrCreate() bir dahaki seed'de bu değeri set etmez;
        // reconcile bloğu bunu definition()'daki değere geri döndürmeli.
        Menu::where('label', 'İlişkiler')->whereNull('parent_id')->update(['permission' => 'yanlis.izin']);

        (new MenuSeeder())->run();

        $iliskiler = Menu::where('label', 'İlişkiler')->whereNull('parent_id')->first();
        $this->assertSame('tenant.view', $iliskiler->permission);
    }
}
