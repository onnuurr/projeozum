<?php

namespace Modules\Marketplace\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pazaryeri (Marketplace) modülünün izin kataloğu + role atamaları (idempotent).
 *
 * RolePermissionSeeder tarafından, roller oluşturulduktan SONRA çağrılır.
 * Atamalar additif (givePermissionTo) olduğundan tekrar çalıştırmak güvenlidir.
 * Superadmin ayrıca Gate::before ile tüm yetenekleri geçer; açık atama netlik içindir.
 */
class MarketplacePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'marketplace.manage'         => 'Pazaryeri Bağlantı Yönet',
            'marketplace.sync'           => 'Portal — Pazaryeri Senkronizasyon',
            'marketplace.view-sales'     => 'Portal — Pazaryeri Satışları Görüntüle',
            'marketplace.catalog.manage' => 'İç Katalog — Kategori/Ürün Pazaryeri Eşleme Yönet',
        ];

        $created = [];
        foreach ($permissions as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $created[] = $perm->name;
        }

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }

        // Tenant rolü kendi pazaryeri credential'larını yönetir + portal satış/senkron işlerini yapar.
        // marketplace.catalog.manage BUNA dahil DEĞİL — iç katalog yönetimi (kategori eşleme,
        // ürün listeleme) yalnız superadmin'e özgü; tenant kullanıcısına verilirse iç admin
        // ekranlarına erişim sızar.
        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        if ($tenantRole) {
            $tenantRole->givePermissionTo(['marketplace.manage', 'marketplace.sync', 'marketplace.view-sales']);
        }
    }
}
