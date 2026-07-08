<?php

namespace Modules\Finance\Providers;

use Modules\Finance\Console\Commands\ExpireProformas;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\Services\EInvoice\NullEInvoiceProvider;
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

        // e-Fatura entegratörü henüz seçilmedi; somut sürücü bağlanana kadar
        // tek binding budur (bkz. EInvoiceProviderInterface doc-block'u).
        $this->app->bind(EInvoiceProviderInterface::class, NullEInvoiceProvider::class);
    }
}
