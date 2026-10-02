<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * İş Ortakları › Bayi Sözleşmeleri: dealer-agreement.manage izni (superadmin'e verilir) ve menü kaydı.
 * Menü, "Başvurular" (/tenants/applications) öğesinin kökü altına, Bayi E-postaları'nın ardına eklenir —
 * kök etiketi "İş Ortağı" / "İş Ortakları" olarak değişebildiği için etikete göre aranmaz. Kök bulunamazsa
 * atlanır (Superadmin › Menüler'den eklenebilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'dealer-agreement.manage', 'guard_name' => 'web'],
            ['display_name' => 'Bayi Sözleşmelerini Yönet'],
        );

        Role::where('name', 'superadmin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rootId = Menu::query()->where('url', '/tenants/applications')->value('parent_id');

        if ($rootId && ! Menu::query()->where('route_name', 'dealer-agreements.index')->exists()) {
            Menu::create([
                'parent_id' => $rootId,
                'label' => 'Bayi Sözleşmeleri',
                'route_name' => 'dealer-agreements.index',
                'url' => '/dealer-agreements',
                'permission' => 'dealer-agreement.manage',
                'sort_order' => (int) Menu::query()->where('parent_id', $rootId)->max('sort_order') + 1,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Menu::query()->where('route_name', 'dealer-agreements.index')->delete();

        Permission::where('name', 'dealer-agreement.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
