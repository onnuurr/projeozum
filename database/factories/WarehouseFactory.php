<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Warehouse;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->city() . ' Deposu',
            'code'       => 'WH-' . strtoupper(Str::random(6)),
            'address'    => $this->faker->streetAddress(),
            'city'       => $this->faker->city(),
            'is_active'  => true,
            'is_default' => false,
        ];
    }

    /** Varsayılan depo (D2 tahsisinde önceliklidir). */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
