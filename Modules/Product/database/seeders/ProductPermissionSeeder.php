<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProductPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'product.view'       => 'Ürünleri Görüntüle',
            'brand.manage'       => 'Marka Yönet',
            'warehouse.manage'   => 'Depo Yönet',
            'stock.manage'       => 'Stok Yönet',
            'price-list.manage'  => 'Fiyat Listesi Yönet',
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
