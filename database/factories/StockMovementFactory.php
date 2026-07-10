<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $qty    = $this->faker->numberBetween(1, 50);
        $before = $this->faker->numberBetween(0, 100);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id'       => Warehouse::factory(),
            'type'               => StockMovement::TYPE_IN,
            'quantity'           => $qty,
            'before_quantity'    => $before,
            'after_quantity'     => $before + $qty,
            'reference_type'     => null,
            'reference_id'       => null,
            'note'               => null,
            'user_id'            => null,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
