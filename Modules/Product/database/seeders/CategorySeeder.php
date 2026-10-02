<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'tisort',   'name' => 'Tişört',   'icon' => 'shirt', 'sort_order' => 1],
            ['slug' => 'gomlek',   'name' => 'Gömlek',   'icon' => 'shirt', 'sort_order' => 2],
            ['slug' => 'pantolon', 'name' => 'Pantolon', 'icon' => 'person-standing', 'sort_order' => 3],
            ['slug' => 'elbise',   'name' => 'Elbise',   'icon' => 'venus', 'sort_order' => 4],
            ['slug' => 'mont',     'name' => 'Mont',     'icon' => 'umbrella', 'sort_order' => 5],
            ['slug' => 'ayakkabi', 'name' => 'Ayakkabı', 'icon' => 'footprints', 'sort_order' => 6],
        ];

        foreach ($categories as $row) {
            Category::updateOrCreate(['slug' => $row['slug']], $row + ['status' => 'active']);
        }
    }
}
