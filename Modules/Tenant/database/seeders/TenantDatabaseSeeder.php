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
    }
}
