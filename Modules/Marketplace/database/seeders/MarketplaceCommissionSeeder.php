<?php

namespace Modules\Marketplace\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketplace\Models\MarketplaceCommissionRate;

/**
 * Marketplace başına default komisyon + birkaç kategori override örneği.
 *
 * NOT: Gerçek oranlar provider sözleşmesine göre değişir; bu seed sadece dev/test
 * için sensible default. Prod'da admin UI'sından override edilir.
 */
class MarketplaceCommissionSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->toDateString();

        $defaults = [
            'trendyol'    => ['commission_rate' => 18.0, 'shipping_rate' => 4.0],
            'hepsiburada' => ['commission_rate' => 17.5, 'shipping_rate' => 3.5],
            'n11'         => ['commission_rate' => 16.0, 'shipping_rate' => 3.0],
            'ciceksepeti' => ['commission_rate' => 20.0, 'shipping_rate' => 5.0],
        ];

        foreach ($defaults as $marketplace => $rates) {
            MarketplaceCommissionRate::updateOrCreate(
                ['marketplace' => $marketplace, 'category_id' => null, 'valid_from' => $today],
                $rates,
            );
        }
    }
}
