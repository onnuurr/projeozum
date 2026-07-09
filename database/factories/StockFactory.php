<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\Warehouse;

class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id'       => Warehouse::factory(),
            'quantity'           => $this->faker->numberBetween(0, 100),
            'reserved_quantity'  => 0,
            'min_quantity'       => 0,
        ];
    }
}
