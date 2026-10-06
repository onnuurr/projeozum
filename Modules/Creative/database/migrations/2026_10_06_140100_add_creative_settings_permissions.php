<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creative ayar sayfası (Faz 4): creative-settings.view (sayfa + çark) ve mockup-settings.manage
 * (Mokap sekmeleri: kategori eşleştirme, stilist seçenekleri, poz etiketleri, otomatik tetik, günlük
 * limit). Yalnız superadmin'e verilir. Seeder canlı DB'yi kendiliğinden güncellemediği için burada.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'creative-settings.view' => 'Creative Ayarlar Sayfası',
        'mockup-settings.manage' => 'Mokap Ayarlarını Yönet',
    ];

    public function up(): void
    {
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        foreach (self::PERMISSIONS as $name => $display) {
            $p = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['display_name' => $display]);
            $superadmin?->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
