<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Başvuru E-postaları sayfası "Bayi E-postaları" oldu ve iade e-postalarını da kapsıyor:
 * order-return-mail.manage izni (superadmin'e verilir) ve menü kaydının etiket / adres güncellemesi
 * (/tenants/applications/mail-templates → /dealer-mail-templates). Menü izni başvuru grubunun
 * (tenant-application-mail.manage) olarak kalır; yalnız iade yetkisi olanlar sayfaya İadeler
 * sayfasındaki butonla ulaşır.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'order-return-mail.manage', 'guard_name' => 'web'],
            ['display_name' => 'Bayi İade E-postalarını Yönet'],
        );

        Role::where('name', 'superadmin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Menu::query()->where('route_name', 'dealer-mail-templates.index')->update([
            'label' => 'Bayi E-postaları',
            'url' => '/dealer-mail-templates',
        ]);
    }

    public function down(): void
    {
        Menu::query()->where('route_name', 'dealer-mail-templates.index')->update([
            'label' => 'Başvuru E-postaları',
            'url' => '/tenants/applications/mail-templates',
        ]);

        Permission::where('name', 'order-return-mail.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
