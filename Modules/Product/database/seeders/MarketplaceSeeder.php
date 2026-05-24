<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Marketplace;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaces = [
            ['key' => 'trendyol',     'name' => 'Trendyol',     'logo_text' => 'T',   'color' => '#f27a1a', 'connected' => true,  'sort_order' => 1],
            ['key' => 'hepsiburada',  'name' => 'Hepsiburada',  'logo_text' => 'HB',  'color' => '#ff6000', 'connected' => true,  'sort_order' => 2],
            ['key' => 'amazon',       'name' => 'Amazon',       'logo_text' => 'AMZ', 'color' => '#ff9900', 'connected' => false, 'sort_order' => 3],
            ['key' => 'n11',          'name' => 'N11',          'logo_text' => 'N11', 'color' => '#f5a623', 'connected' => true,  'sort_order' => 4],
            ['key' => 'gittigidiyor', 'name' => 'GittiGidiyor', 'logo_text' => 'GG',  'color' => '#ed1c24', 'connected' => false, 'sort_order' => 5],
        ];

        foreach ($marketplaces as $row) {
            Marketplace::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
