<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * İş Ortakları › Bayi Portalı (Bagisto özellik anahtarları) sayfası: bagisto.manage izni (superadmin'e
 * verilir) ve menü kaydı. Menü "İş Ortakları" kökü yoksa atlanır (Superadmin › Menüler'den eklenebilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'bagisto.manage', 'guard_name' => 'web'],
            ['display_name' => 'Bayi Portalı (Bagisto) Ayarlarını Yönet'],
        );

        Role::where('name', 'superadmin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $root = Menu::query()->whereNull('parent_id')->where('label', 'İş Ortakları')->first();

        if ($root && ! Menu::query()->where('route_name', 'bagisto.settings.index')->exists()) {
            Menu::create([
                'parent_id' => $root->id,
                'label' => 'Bayi Portalı',
                'route_name' => 'bagisto.settings.index',
                'url' => '/bagisto/settings',
                'permission' => 'bagisto.manage',
                'sort_order' => (int) Menu::query()->where('parent_id', $root->id)->max('sort_order') + 1,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Menu::query()->where('route_name', 'bagisto.settings.index')->delete();

        Permission::where('name', 'bagisto.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
