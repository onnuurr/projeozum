<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * İş Ortakları › Başvuru E-postaları (bayi portalı başvuru e-posta metinleri) sayfası:
 * tenant-application-mail.manage izni (superadmin'e verilir) ve menü kaydı. Menü, "Başvurular"
 * (/tenants/applications) öğesinin kökü altına eklenir — kök etiketi "İş Ortağı" / "İş Ortakları"
 * olarak değişebildiği için etikete göre aranmaz. Kök bulunamazsa atlanır (Superadmin › Menüler).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'tenant-application-mail.manage', 'guard_name' => 'web'],
            ['display_name' => 'İş Ortağı Başvuru E-postalarını Yönet'],
        );

        Role::where('name', 'superadmin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rootId = Menu::query()->where('url', '/tenants/applications')->value('parent_id');

        if ($rootId && ! Menu::query()->where('route_name', 'dealer-mail-templates.index')->exists()) {
            Menu::create([
                'parent_id' => $rootId,
                'label' => 'Başvuru E-postaları',
                'route_name' => 'dealer-mail-templates.index',
                'url' => '/tenants/applications/mail-templates',
                'permission' => 'tenant-application-mail.manage',
                'sort_order' => (int) Menu::query()->where('parent_id', $rootId)->max('sort_order') + 1,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Menu::query()->where('route_name', 'dealer-mail-templates.index')->delete();

        Permission::where('name', 'tenant-application-mail.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
