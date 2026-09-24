<?php

namespace Modules\Atelier\Providers;

use App\Contracts\ProductBomMaterialSource as ProductBomMaterialSourceContract;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Atelier\Services\ProductBomMaterialSource;

class AtelierServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Atelier';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'atelier';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Product'ın reçete materyallerine Atelier'e bağımlı olmadan erişmesi için
        // (bkz. App\Contracts\ProductBomMaterialSource).
        $this->app->bind(ProductBomMaterialSourceContract::class, ProductBomMaterialSource::class);
    }

    /**
     * Define module schedules.
     *
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
