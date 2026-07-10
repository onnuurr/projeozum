<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Category;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'parent_id'  => null,
            'name'       => ucfirst($name),
            'slug'       => Str::slug($name) . '-' . Str::random(5),
            'sort_order' => 0,
        ];
    }
}
