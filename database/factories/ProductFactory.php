<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'category_id'   => Category::factory(),
            'brand_id'      => null,
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(5),
            'sku'           => 'PRD-' . strtoupper(Str::random(8)),
            'gender'        => 'Unisex',
            'price'         => $this->faker->randomFloat(2, 50, 2000),
            'review_count'  => 0,
            'is_new'        => false,
            'free_shipping' => false,
        ];
    }
}
