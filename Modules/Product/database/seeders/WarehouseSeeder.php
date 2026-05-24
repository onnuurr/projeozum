<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'code'      => 'ANA01',
                'name'      => 'Ana Depo',
                'city'      => 'İstanbul',
                'address'   => null,
                'is_active' => true,
            ],
        ];

        foreach ($warehouses as $row) {
            Warehouse::updateOrCreate(['code' => $row['code']], $row);
        }
    }
}
