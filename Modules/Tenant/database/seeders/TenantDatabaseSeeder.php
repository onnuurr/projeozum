<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \Modules\Tenant\database\seeders\TenantTypeSeeder::class,
            \Modules\Tenant\database\seeders\TenantPermissionSeeder::class,
        ]);
    }
}
