<?php

namespace Modules\Superadmin\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperadminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'logs.view'       => 'Log Görüntüleme',
            'settings.manage' => 'Sistem Ayarları Yönet',
            'menu.manage'     => 'Menü Yönet',
            'rbac.manage'     => 'Rol & İzin Yönet',
            'users.view'      => 'Kullanıcıları Görüntüle',
            'users.manage'    => 'Kullanıcı Yönet',
            'backups.view'    => 'Yedeklemeleri Görüntüle',
            'backups.manage'  => 'Yedekleme Tetikle',
            'architecture-doctor.view' => 'Mimari Doktor Raporunu Görüntüle',
        ];

        $created = [];
        foreach ($permissions as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            // Mevcut bir permission ise display_name'i güncelle.
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $created[] = $perm->name;
        }

        // Superadmin rolüne hepsini ata.
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }
    }
}
