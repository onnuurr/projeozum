<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $qty   = $this->faker->numberBetween(1, 5);
        $price = $this->faker->randomFloat(2, 50, 800);

        return [
            'order_id'      => Order::factory(),
            'product_id'    => null, // Test setup'ında doldurulur (Product factory varsa).
            'product_name'  => $this->faker->words(3, true),
            'product_brand' => $this->faker->company(),
            'product_image' => null,
            'color'         => $this->faker->optional()->safeColorName(),
            'size'          => $this->faker->randomElement([null, 'S', 'M', 'L', 'XL']),
            'qty'           => $qty,
            'unit_price'    => $price,
            'total_price'   => $qty * $price,
        ];
    }
}
