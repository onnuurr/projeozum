<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Süper Admin › Dil Dosyaları: translations.manage izni (superadmin'e verilir) ve
 * "Süper Admin Paneli" altına menü kaydı. Seeder'lar (SuperadminPermissionSeeder,
 * MenuSeeder) da güncellendi; canlı DB seeder'ı geriye dönük almadığı için burada da eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'translations.manage', 'guard_name' => 'web'],
            ['display_name' => 'Dil Dosyalarını Yönet'],
        );

        Role::where('name', 'superadmin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Menu::firstOrCreate(
            ['route_name' => 'superadmin.translations.index'],
            [
                'parent_id' => Menu::where('route_name', 'superadmin.index')->value('id'),
                'label' => 'Dil Dosyaları',
                'icon' => null,
                'url' => null,
                'permission' => 'translations.manage',
                'sort_order' => 7,
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        Menu::where('route_name', 'superadmin.translations.index')->delete();

        Permission::where('name', 'translations.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
