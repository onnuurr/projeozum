<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenant\Models\TenantType;

class TenantTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code'            => 'DEALER',
                'name'            => 'Bayi',
                'description'     => 'Stoklu bayi: ana firmadan toplu alır, kendi stoğunda tutar.',
                'price_list_type' => TenantType::PRICE_DEALER,
                'sort_order'      => 10,
            ],
            [
                'code'            => 'DROPSHIP',
                'name'            => 'Dropship',
                'description'     => 'Stoksuz iş ortağı: sipariş geldikçe ana firmadan sevkiyat yapılır.',
                'price_list_type' => TenantType::PRICE_DROPSHIP,
                'sort_order'      => 20,
            ],
            [
                'code'            => 'WHOLESALE',
                'name'            => 'Toptan',
                'description'     => 'Toptan müşteri: yüksek hacimli, özel fiyatlandırma.',
                'price_list_type' => TenantType::PRICE_DEALER,
                'sort_order'      => 30,
            ],
        ];

        foreach ($types as $row) {
            TenantType::updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true],
            );
        }
    }
}
