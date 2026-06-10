<?php

namespace Modules\Creative\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Creative\Models\BrandKit;

class BrandKitSeeder extends Seeder
{
    public function run(): void
    {
        // Tek varsayılan kit garantisi: yoksa oluştur, varsa dokunma.
        if (BrandKit::query()->where('is_default', true)->exists()) {
            return;
        }

        BrandKit::create([
            'name'       => 'Varsayılan Marka',
            'is_default' => true,
            'palette'    => [
                'primary'    => '#111827',
                'secondary'  => '#6b7280',
                'accent'     => '#2563eb',
                'background' => '#ffffff',
                'text'       => '#111827',
            ],
            'typography' => [
                // Modül içi DejaVu fontları; özel font yüklenince burası güncellenir.
                'regular' => null,
                'bold'    => null,
            ],
            'logos'   => [],
            'spacing' => [
                'sm' => 8,
                'md' => 16,
                'lg' => 32,
            ],
        ]);
    }
}
