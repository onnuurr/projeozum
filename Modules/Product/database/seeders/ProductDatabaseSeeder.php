<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;

class ProductDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // NOT: Pazaryeri kataloğu (MarketplaceSeeder) Marketplace modülüne taşındı:
        // Modules\Marketplace\database\seeders\MarketplaceDatabaseSeeder.
        $this->call([
            \Modules\Product\database\seeders\BrandSeeder::class,
            \Modules\Product\database\seeders\CategorySeeder::class,
            \Modules\Product\database\seeders\ProductSeeder::class,
            \Modules\Product\database\seeders\WarehouseSeeder::class,
            \Modules\Product\database\seeders\StockSeeder::class,
            \Modules\Product\database\seeders\CarrierSeeder::class,
            \Modules\Product\database\seeders\ProductPermissionSeeder::class,
        ]);
    }
}
