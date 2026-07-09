<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size'       => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
            'color_name' => $this->faker->safeColorName(),
            'color_hex'  => $this->faker->hexColor(),
            'sku'        => 'VAR-' . strtoupper(Str::random(8)),
            'price'      => $this->faker->randomFloat(2, 50, 2000),
            // stocks tablosu source-of-truth; bu alan denormalize cache (StockService resync eder).
            'stock'      => 0,
            'sort_order' => 0,
        ];
    }
}
