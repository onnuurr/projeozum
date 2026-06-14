<?php

namespace Modules\Atelier\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Atelier\Models\Operation;

class OperationSeeder extends Seeder
{
    public function run(): void
    {
        $operations = [
            ['code' => 'kesim',  'name' => 'Kesim',          'default_location' => 'in_house', 'sort_order' => 10],
            ['code' => 'dikim',  'name' => 'Dikim',          'default_location' => 'fason',    'sort_order' => 20],
            ['code' => 'baski',  'name' => 'Baskı',          'default_location' => 'fason',    'sort_order' => 30],
            ['code' => 'nakis',  'name' => 'Nakış',          'default_location' => 'fason',    'sort_order' => 40],
            ['code' => 'utu',    'name' => 'Ütü',            'default_location' => 'in_house', 'sort_order' => 50],
            ['code' => 'kalite', 'name' => 'Kalite Kontrol', 'default_location' => 'in_house', 'sort_order' => 60],
            ['code' => 'paket',  'name' => 'Paketleme',      'default_location' => 'in_house', 'sort_order' => 70],
        ];

        foreach ($operations as $op) {
            Operation::firstOrCreate(['code' => $op['code']], $op);
        }
    }
}
