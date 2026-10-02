<?php

namespace Modules\Product\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Product\Console\RenumberCategoriesCommand;
use Modules\Product\Console\SyncCategoryStatusCommand;
use Modules\Product\Console\SyncVariantStockCommand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\CategoryStatusService;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\GeminiProductDescriptionGenerator;

class ProductServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Product';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'product';

    public function register(): void
    {
        parent::register();

        $this->app->bind(ProductDescriptionGenerator::class, GeminiProductDescriptionGenerator::class);

        $this->commands([
            SyncVariantStockCommand::class,
            SyncCategoryStatusCommand::class,
            RenumberCategoriesCommand::class,
        ]);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerCategoryStatusSync();
    }

    /**
     * Kategori durumu otomatik: yayında ürünü (alt kategoriler dahil) yoksa pasif (CategoryStatusService).
     * Model olayları — ürün yayına alma / kategori değiştirme / silme hangi yoldan gelirse gelsin yakalanır.
     */
    private function registerCategoryStatusSync(): void
    {
        $sync = fn () => app(CategoryStatusService::class)->syncAll();

        Product::saved(function (Product $product) use ($sync) {
            if (app(CategoryStatusService::class)->productAffectsCategories($product)) {
                $sync();
            }
        });
        Product::deleted($sync);
        Product::restored($sync);

        // Kategori kaydı (yeni, taşıma, elle pasif/aktif) ve silme — servis kendi kayıtlarında yeniden tetiklenmez
        Category::saved($sync);
        Category::deleted($sync);
        Category::restored($sync);
    }

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
