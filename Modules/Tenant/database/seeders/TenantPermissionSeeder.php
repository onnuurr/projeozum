<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminPermissions = [
            'tenant.view'              => 'Tenant\'ları Görüntüle',
            'tenant.manage'            => 'Tenant Yönet',
            'tenant-type.manage'       => 'Tenant Tipi Yönet',
            'tenant-access.manage'     => 'Tenant Erişimi Yönet',
            'tenant.product.customize' => 'Tenant\'a Özel Ürün Metni Yaz',
            'marketplace.manage'       => 'Pazaryeri Bağlantı Yönet',
            'portal.users.manage'      => 'Portal — Kullanıcı Yönet',
        ];

        $tenantPortalPermissions = [
            'portal.access'         => 'Portal Erişimi',
            'portal.orders.view'    => 'Portal — Siparişleri Görüntüle',
            'portal.invoices.view'  => 'Portal — Faturaları Görüntüle',
            'portal.credit.view'    => 'Portal — Kredi Hareketleri',
            'portal.catalog.view'   => 'Portal — Katalog Görüntüle',
            'portal.checkout'       => 'Portal — Dropship Sipariş Aç',
            'marketplace.sync'      => 'Portal — Pazaryeri Senkronizasyon',
            'marketplace.view-sales'=> 'Portal — Pazaryeri Satışları Görüntüle',
            'portal.financials.view'=> 'Portal — Kâr/Zarar Dashboard',
            'portal.calculator.use' => 'Portal — Kâr Hesabı Kullan',
            'portal.feed.access'    => 'Portal — XML Feed URL Erişimi',
        ];

        $allCreated = [];
        foreach ([...$adminPermissions, ...$tenantPortalPermissions] as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $allCreated[] = $perm->name;
        }

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($allCreated);
        }

        // Tenant rolü kendi portal işlerini ve pazaryeri credential'larını yönetebilsin.
        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        if ($tenantRole) {
            $tenantRole->givePermissionTo([
                'marketplace.manage',
                'portal.access',
                'portal.orders.view',
                'portal.invoices.view',
                'portal.credit.view',
                'portal.catalog.view',
                'portal.checkout',
                'marketplace.sync',
                'marketplace.view-sales',
                'portal.financials.view',
                'portal.calculator.use',
                'portal.feed.access',
                'portal.users.manage',
            ]);
        }

        // Alt kullanıcılar için baseline rol: yalnız giriş (portal.access).
        // Granüler portal.* izinleri kullanıcıya doğrudan atanır (TenantUserService).
        $tenantUserRole = Role::where('name', 'tenant-user')->where('guard_name', 'web')->first();
        if ($tenantUserRole) {
            $tenantUserRole->givePermissionTo('portal.access');
        }
    }
}
