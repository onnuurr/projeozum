<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // RBAC: roller + izin kataloğu (tüm modüller). Demo iş verisi seed'lerinden bağımsız.
        $this->call(RolePermissionSeeder::class);

        // Creative: varsayılan marka kiti (render/AI/caption token kaynağı).
        $this->call(\Modules\Creative\database\seeders\BrandKitSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
