<?php

namespace Modules\Finance\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FinancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'finance.manage' => 'Finans Modülünü Yönet',
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

        // Yalnızca superadmin/iç kullanım — tenant rolüne asla verilmez.
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }
    }
}
