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
            'atelier.view'              => 'Atölye Görüntüle',
            'atelier.material.manage'   => 'Atölye Hammadde Yönet',
            'atelier.bom.manage'        => 'Atölye Reçete (BOM) Yönet',
            'atelier.operation.manage'  => 'Atölye Operasyon Yönet',
            'atelier.fason.manage'      => 'Atölye Fasoncu Yönet',
            'atelier.production.manage' => 'Atölye Üretim Emri Yönet',
            'atelier.pattern.view'      => 'Atölye Kalıp Kütüphanesi Görüntüle',
            'atelier.pattern.manage'    => 'Atölye Kalıp Yönet',
            'atelier.conversion.manage' => 'Atölye PDF→DXF Dönüştürme Yönet',
            'atelier.design.manage'     => 'Atölye AI Konsept Yönet',
            'atelier.assignment.manage' => 'Atölye Atama Yönet',
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

        // Yönetici rolü (Roller ekranından oluşturulmuş iç rol) Atölye'yi salt okur.
        $yonetici = Role::where('name', 'yonetici')->where('guard_name', 'web')->first();
        if ($yonetici) {
            $yonetici->givePermissionTo(['atelier.view', 'atelier.pattern.view']);
        }
    }
}
