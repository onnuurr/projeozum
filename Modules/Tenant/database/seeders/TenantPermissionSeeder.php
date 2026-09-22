<?php

namespace Modules\Tenant\database\seeders;

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
        ];

        $allCreated = [];
        foreach ($adminPermissions as $name => $displayName) {
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
    }
}
