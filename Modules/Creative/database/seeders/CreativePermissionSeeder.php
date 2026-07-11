<?php

namespace Modules\Creative\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreativePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'creative.view'            => 'Creative Görüntüle',
            'creative.generate'        => 'Creative Görsel Üret',
            'creative.approve'         => 'Creative Asset Onayla',
            'creative.asset.manage'    => 'Creative Manken/Poz/Tryon Yönet',
            'creative.template.manage' => 'Creative Şablon Yönet',
            'creative.brandkit.manage' => 'Creative Marka Kiti Yönet',
            'creative.rejection-reasons.manage' => 'Creative Ret Seçim Maddeleri Yönet',
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
