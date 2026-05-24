<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantPriceList;

class TenantPriceListFactory extends Factory
{
    protected $model = TenantPriceList::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'product_group_id' => null,
            'discount_rate'    => $this->faker->randomFloat(2, 5, 30),
            'special_price'    => null,
            'valid_from'       => now(),
            'valid_until'      => now()->addMonths(3),
            'is_active'        => true,
        ];
    }
}
