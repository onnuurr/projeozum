<?php

namespace Modules\Superadmin\database\seeders;

use Illuminate\Database\Seeder;

class SuperadminDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
        ]);
    }
}
