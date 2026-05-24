<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'tisort',   'name' => 'Tişört',   'icon' => '👕', 'sort_order' => 1],
            ['slug' => 'gomlek',   'name' => 'Gömlek',   'icon' => '👔', 'sort_order' => 2],
            ['slug' => 'pantolon', 'name' => 'Pantolon', 'icon' => '👖', 'sort_order' => 3],
            ['slug' => 'elbise',   'name' => 'Elbise',   'icon' => '👗', 'sort_order' => 4],
            ['slug' => 'mont',     'name' => 'Mont',     'icon' => '🧥', 'sort_order' => 5],
            ['slug' => 'ayakkabi', 'name' => 'Ayakkabı', 'icon' => '👟', 'sort_order' => 6],
        ];

        foreach ($categories as $row) {
            Category::updateOrCreate(['slug' => $row['slug']], $row + ['status' => 'active']);
        }
    }
}
