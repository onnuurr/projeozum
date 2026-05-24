<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Tenant\Models\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'code'              => strtoupper(Str::random(6)),
            'name'              => $name,
            'slug'              => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'legal_name'        => $name . ' A.Ş.',
            'email'             => $this->faker->companyEmail(),
            'phone'             => $this->faker->phoneNumber(),
            'tax_number'        => $this->faker->numerify('##########'),
            'tax_office'        => $this->faker->city() . ' VD',
            'address'           => $this->faker->address(),
            'city'              => $this->faker->city(),
            'country'           => 'TR',
            'credit_limit'      => $this->faker->randomFloat(2, 1000, 100000),
            'current_balance'   => 0,
            'payment_term_days' => $this->faker->randomElement([0, 7, 15, 30]),
            'discount_rate'     => $this->faker->randomFloat(2, 0, 25),
            'is_active'         => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
