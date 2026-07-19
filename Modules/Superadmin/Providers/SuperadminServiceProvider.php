<?php

namespace Modules\Superadmin\Providers;

use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Console\Commands\BackupRunCommand;
use Modules\Superadmin\Models\Setting;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Throwable;

class SuperadminServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Superadmin';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'superadmin';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        BackupRunCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->overrideAppConfigFromSettings();
    }

    /**
     * superadmin_settings tablosundaki general.systemName değerini
     * config('app.name') üzerine yazar. Tablo yoksa (fresh install /
     * migrasyon öncesi) sessizce env varsayılanı kullanılmaya devam eder.
     */
    private function overrideAppConfigFromSettings(): void
    {
        try {
            if (! Schema::hasTable('superadmin_settings')) {
                return;
            }

            $general = Setting::allGrouped()['general'] ?? [];

            $systemName = $general['systemName'] ?? null;
            if (is_string($systemName) && $systemName !== '') {
                config(['app.name' => $systemName]);
            }
        } catch (Throwable) {
            // DB erişilemezse config olduğu gibi kalsın.
        }
    }
}
