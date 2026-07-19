<?php

namespace Modules\Tenant\database\seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantTypeSeeder::class,
            TenantPermissionSeeder::class,
            MarketplaceCommissionSeeder::class,
        ]);
    }
}
