<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;

class TenantInvoiceFactory extends Factory
{
    protected $model = TenantInvoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_id'  => null,
            'amount'    => $this->faker->randomFloat(2, 100, 50000),
            'currency'  => 'TRY',
            'status'    => 'pending',
            'due_date'  => now()->addDays(30),
            'note'      => $this->faker->optional()->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }
}
