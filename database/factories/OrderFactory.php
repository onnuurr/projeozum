<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 5000);
        $shipping = $this->faker->randomElement([0, 49.90, 79.90, 129.90]);

        return [
            'order_no'       => 'SIP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'user_id'        => User::factory(),
            'tenant_id'      => null,
            'order_type'     => Order::TYPE_B2C,
            'shipping_info'  => [
                'address' => ['name' => 'Test', 'phone' => '+90 555 555 55 55', 'street' => '...', 'city' => 'İstanbul'],
                'shipping_method' => 'standard',
            ],
            'payment_method' => $this->faker->randomElement(['card', 'bank', 'cod']),
            'note'           => null,
            'subtotal'       => $subtotal,
            'shipping_fee'   => $shipping,
            'total'          => $subtotal + $shipping,
            'status'         => 'pending',
        ];
    }

    public function dropship(?Tenant $tenant = null): static
    {
        return $this->state(fn () => [
            'tenant_id'  => $tenant?->id ?? Tenant::factory(),
            'order_type' => Order::TYPE_DROPSHIP,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return $this->state(fn () => [
            'tenant_id'  => $tenant->id,
            'user_id'    => $user->id,
            'order_type' => Order::TYPE_DROPSHIP,
        ]);
    }
}
