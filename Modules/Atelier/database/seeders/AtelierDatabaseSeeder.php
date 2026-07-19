<?php

namespace Modules\Atelier\database\seeders;

use Illuminate\Database\Seeder;

class AtelierDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            \Modules\Atelier\database\seeders\AtelierPermissionSeeder::class,
            \Modules\Atelier\database\seeders\OperationSeeder::class,
        ]);
    }
}
