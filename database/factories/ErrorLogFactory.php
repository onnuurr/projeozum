<?php

namespace Database\Factories;

use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ErrorLogFactory extends Factory
{
    protected $model = ErrorLog::class;

    public function definition(): array
    {
        return [
            'module'          => $this->faker->randomElement(['auth', 'product', 'atelier', 'superadmin', null]),
            'level'           => $this->faker->randomElement(['warning', 'error', 'critical']),
            'message'         => $this->faker->sentence(),
            'exception_class' => $this->faker->randomElement([
                'RuntimeException',
                'InvalidArgumentException',
                'Illuminate\\Database\\QueryException',
            ]),
            'file'            => '/var/www/html/app/Http/Controllers/SomeController.php',
            'line'            => $this->faker->numberBetween(10, 500),
            'trace'           => "#0 /app/Http/Controllers/SomeController.php(42): handle()\n#1 /vendor/laravel/framework/src/Illuminate/Routing/Route.php(238): run()\n#2 {main}",
            'url'             => '/some/path',
            'method'          => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'causer_id'       => null,
            'ip_address'      => $this->faker->ipv4(),
            'context'         => null,
            'fingerprint'     => $this->faker->md5(),
            'occurred_at'     => now(),
        ];
    }
}
