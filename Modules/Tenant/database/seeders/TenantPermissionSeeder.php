<?php

namespace Modules\Tenant\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'tenant.view'          => 'Tenant\'ları Görüntüle',
            'tenant.manage'        => 'Tenant Yönet',
            'tenant-type.manage'   => 'Tenant Tipi Yönet',
            'tenant-access.manage' => 'Tenant Erişimi Yönet',
            'marketplace.manage'   => 'Pazaryeri Bağlantı Yönet',
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

        // Tenant rolü kendi pazaryeri credential'larını yönetebilsin (controller scope'lu).
        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        if ($tenantRole) {
            $tenantRole->givePermissionTo('marketplace.manage');
        }
    }
}
