<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'module'      => $this->faker->randomElement(['auth', 'product', 'atelier', 'superadmin']),
            'action'      => $this->faker->randomElement(['auth.login', 'auth.logout', 'product.created', 'product.updated']),
            'description' => $this->faker->sentence(),
            'subject_type' => null,
            'subject_id'   => null,
            'causer_id'    => null,
            'causer_label' => null,
            'ip_address'   => $this->faker->ipv4(),
            'user_agent'   => $this->faker->userAgent(),
            'properties'   => null,
            'level'        => $this->faker->randomElement(['info', 'notice', 'warning']),
        ];
    }
}
