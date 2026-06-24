<?php

namespace Modules\Atelier\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AtelierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'atelier.view'              => 'Atelier Görüntüle',
            'atelier.material.manage'   => 'Atelier Hammadde Yönet',
            'atelier.bom.manage'        => 'Atelier Reçete (BOM) Yönet',
            'atelier.operation.manage'  => 'Atelier Operasyon Yönet',
            'atelier.fason.manage'      => 'Atelier Fasoncu Yönet',
            'atelier.production.manage' => 'Atelier Üretim Emri Yönet',
            'atelier.pattern.view'      => 'Atelier Kalıp Kütüphanesi Görüntüle',
            'atelier.pattern.manage'    => 'Atelier Kalıp Yönet',
            'atelier.conversion.manage' => 'Atelier PDF→DXF Dönüştürme Yönet',
            'atelier.design.manage'     => 'Atelier AI Konsept Yönet',
            'atelier.assignment.manage' => 'Atelier Atölye Atama Yönet',
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

        // Yalnızca superadmin/iç kullanım — tenant rolüne verilmez.
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }
    }
}
