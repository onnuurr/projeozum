<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantTypeSeeder::class,
            TenantPermissionSeeder::class,
        ]);

        // NOT: Pazaryeri komisyon oranları (MarketplaceCommissionSeeder) Marketplace
        // modülüne taşındı: Modules\Marketplace\database\seeders\MarketplaceDatabaseSeeder.
    }
}
