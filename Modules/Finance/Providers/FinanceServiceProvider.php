<?php

namespace Modules\Finance\Providers;

use Modules\Finance\Console\Commands\ExpireProformas;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\Services\EInvoice\NullEInvoiceProvider;
use Modules\Finance\Services\EInvoice\TrendyolEFaturamProvider;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FinanceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Finance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'finance';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExpireProformas::class,
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

        // e-Fatura entegratörü: kimlik bilgisi (email+password) tanımlıysa gerçek
        // Trendyol e-Faturam sürücüsü, yoksa hiçbir dış istek atmayan NullEInvoiceProvider.
        $this->app->bind(EInvoiceProviderInterface::class, function ($app) {
            $config = (array) config('finance.einvoice', []);

            $usable = ($config['driver'] ?? null) === 'trendyol'
                && ! empty($config['email'])
                && ! empty($config['password']);

            return $usable
                ? new TrendyolEFaturamProvider($config)
                : new NullEInvoiceProvider();
        });
    }
}
