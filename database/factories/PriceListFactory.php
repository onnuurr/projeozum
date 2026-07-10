<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\ProductVariant;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'type'               => PriceList::TYPE_DROPSHIP,
            'price'              => $this->faker->randomFloat(2, 10, 1000),
            'currency'           => 'TRY',
            'is_active'          => true,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
