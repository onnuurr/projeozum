<?php

namespace Modules\Superadmin\Providers;

use App\Contracts\PlatformSettings;
use App\Contracts\TranslationSource;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Console\Commands\BackupRunCommand;
use Modules\Superadmin\Console\Commands\CheckVersionsCommand;
use Modules\Superadmin\Contracts\ArchitectureDoctorFixerRunner;
use Modules\Superadmin\Models\Setting;
use Modules\Superadmin\Services\ArchitectureDoctorFixer\ClaudeCliFixerRunner;
use Modules\Superadmin\Services\ArchitectureDoctorFixer\MockFixerRunner;
use Modules\Superadmin\Services\PlatformSettingsStore;
use Modules\Superadmin\Services\Translations\SaasTranslationSource;
use Modules\Superadmin\Services\Translations\TranslationService;
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
        CheckVersionsCommand::class,
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

    public function register(): void
    {
        parent::register();

        // Superadmin'e bağımlı olamayan modüllerin (Bagisto) ayar erişimi — bkz. App\Contracts\PlatformSettings.
        $this->app->bind(PlatformSettings::class, PlatformSettingsStore::class);

        // Dil Dosyaları editörünün kaynakları etiketle toplanır (Bagisto kendi kaynağını ekler).
        $this->app->tag([SaasTranslationSource::class], TranslationSource::TAG);
        $this->app->bind(TranslationService::class, fn ($app) => new TranslationService($app->tagged(TranslationSource::TAG)));

        // enabled + gerçek sürücü isteniyorsa Claude CLI, aksi halde (varsayılan)
        // hiçbir şeye dokunmayan Mock — Modules/Creative'deki Gemini/Mock
        // config-switch deseninin birebir aynısı.
        $this->app->bind(ArchitectureDoctorFixerRunner::class, function ($app) {
            $useReal = config('superadmin.architecture_doctor.enabled');

            return $app->make($useReal ? ClaudeCliFixerRunner::class : MockFixerRunner::class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->overrideAppConfigFromSettings();
    }

    /**
     * superadmin_settings tablosundaki general.systemName değerini ilgili
     * config anahtarının üzerine yazar. Tablo yoksa (fresh install /
     * migrasyon öncesi) sessizce env/kod varsayılanı kullanılmaya devam eder.
     */
    private function overrideAppConfigFromSettings(): void
    {
        try {
            if (! Schema::hasTable('superadmin_settings')) {
                return;
            }

            $grouped = Setting::allGrouped();

            $systemName = $grouped['general']['systemName'] ?? null;
            if (is_string($systemName) && $systemName !== '') {
                config(['app.name' => $systemName]);
            }
        } catch (Throwable) {
            // DB erişilemezse config olduğu gibi kalsın.
        }
    }
}
