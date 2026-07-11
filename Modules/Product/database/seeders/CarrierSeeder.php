<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Carrier;

/**
 * Türkiye'de yaygın kargo firmaları. idempotent (code üzerinden firstOrCreate).
 * Superadmin bu listeyi carrier.manage ekranından düzenler/genişletir.
 */
class CarrierSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            ['code' => 'ARAS',        'name' => 'Aras Kargo',        'sort' => 10],
            ['code' => 'YURTICI',     'name' => 'Yurtiçi Kargo',     'sort' => 20],
            ['code' => 'MNG',         'name' => 'MNG Kargo',         'sort' => 30],
            ['code' => 'SURAT',       'name' => 'Sürat Kargo',       'sort' => 40],
            ['code' => 'PTT',         'name' => 'PTT Kargo',         'sort' => 50],
            ['code' => 'UPS',         'name' => 'UPS Kargo',         'sort' => 60],
            ['code' => 'SENDEO',      'name' => 'Sendeo',            'sort' => 70],
            ['code' => 'HEPSIJET',    'name' => 'HepsiJet',          'sort' => 80],
            ['code' => 'TRENDYOL',    'name' => 'Trendyol Express',  'sort' => 90],
        ];

        foreach ($carriers as $c) {
            Carrier::firstOrCreate(
                ['code' => $c['code']],
                ['name' => $c['name'], 'is_active' => true, 'sort_order' => $c['sort']],
            );
        }
    }
}
