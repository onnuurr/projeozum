<?php

namespace Modules\Bagisto\Providers;

use Modules\Bagisto\Console\SyncExistingProductsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BagistoServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bagisto';

    protected string $nameLower = 'bagisto';

    protected array $commands = [
        SyncExistingProductsCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
