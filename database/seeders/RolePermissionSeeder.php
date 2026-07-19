<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Uygulama genelinde RBAC'in tek doğruluk kaynağı (idempotent).
 *
 *   1. Rolleri oluşturur: superadmin, tenant (guard: web).
 *   2. Silinmiş modüllerden kalan orphan izinleri budar.
 *   3. Her modülün izin seeder'ını çağırır (izinler + role atamaları orada yapılır).
 *
 * Superadmin ayrıca `Gate::before` (AppServiceProvider) ile tüm yetenekleri geçer; buradaki
 * açık atamalar yine de netlik/dökümantasyon için korunur.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Silinmiş/yeniden adlandırılmış izinler.
     *   - ads.manage, ai.model.create: silinmiş Ads/AiStudio modüllerinden kalan kalıntılar.
     *   - creative.manage, atelier.manage: hibrit granüler izinlere bölündüğü için emekli edildi
     *     (bkz. CreativePermissionSeeder / AtelierPermissionSeeder).
     */
    private const ORPHAN_PERMISSIONS = [
        'ads.manage',
        'ai.model.create',
        'creative.manage',
        'atelier.manage',
    ];

    public function run(): void
    {
        // 1) Roller — izin seeder'ları bunlara atama yaptığı için ÖNCE oluşturulur.
        foreach (['superadmin', 'tenant', 'tenant-user'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // 2) Orphan izin temizliği.
        Permission::whereIn('name', self::ORPHAN_PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        // 3) Modül izin seeder'ları (izin kataloğu + role atamaları).
        $this->call([
            \Modules\Product\database\seeders\ProductPermissionSeeder::class,
            \Modules\Tenant\database\seeders\TenantPermissionSeeder::class,
            \Modules\Creative\database\seeders\CreativePermissionSeeder::class,
            \Modules\Atelier\database\seeders\AtelierPermissionSeeder::class,
            \Modules\Superadmin\database\seeders\SuperadminPermissionSeeder::class,
            \Modules\Finance\database\seeders\FinancePermissionSeeder::class,
        ]);
    }
}
