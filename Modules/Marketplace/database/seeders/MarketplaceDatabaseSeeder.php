<?php

namespace Modules\Marketplace\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Pazaryeri modülü demo/iş verisi seed'i.
 *
 *   - MarketplaceSeeder: pazaryeri kataloğu (trendyol, hepsiburada, ...).
 *   - MarketplaceCommissionSeeder: pazaryeri başına default komisyon oranları.
 *
 * NOT: İzinler RolePermissionSeeder → MarketplacePermissionSeeder üzerinden seed edilir,
 * burada tekrar çağrılmaz.
 */
class MarketplaceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MarketplaceSeeder::class,
            MarketplaceCommissionSeeder::class,
        ]);
    }
}
