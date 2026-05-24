<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['slug' => 'nike',       'name' => 'Nike',       'sort_order' => 1],
            ['slug' => 'adidas',     'name' => 'Adidas',     'sort_order' => 2],
            ['slug' => 'puma',       'name' => 'Puma',       'sort_order' => 3],
            ['slug' => 'lc-waikiki', 'name' => 'LC Waikiki', 'sort_order' => 4],
            ['slug' => 'koton',      'name' => 'Koton',      'sort_order' => 5],
            ['slug' => 'mavi',       'name' => 'Mavi',       'sort_order' => 6],
            ['slug' => 'zara',       'name' => 'Zara',       'sort_order' => 7],
            ['slug' => 'levis',      'name' => 'Levi\'s',    'sort_order' => 8],
        ];

        foreach ($brands as $row) {
            Brand::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
