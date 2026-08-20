<?php

namespace Modules\TrainingIngest\Providers;

use Modules\TrainingIngest\Console\Commands\TrainingExportCommand;
use Modules\TrainingIngest\Console\Commands\TrainingIngestCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TrainingIngestServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'TrainingIngest';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'training-ingest';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        TrainingIngestCommand::class,
        TrainingExportCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
    ];
}
