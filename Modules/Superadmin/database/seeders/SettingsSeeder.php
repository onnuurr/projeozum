<?php

namespace Modules\Superadmin\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Superadmin\Http\Controllers\SettingsController;
use Modules\Superadmin\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = (new SettingsController())->defaultSettings();

        foreach ($defaults as $group => $values) {
            foreach ($values as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
