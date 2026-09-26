<?php

namespace Modules\Bagisto\Providers;

use Modules\Bagisto\Console\SyncCategoriesCommand;
use Modules\Bagisto\Console\SyncExistingProductsCommand;
use Modules\Bagisto\Jobs\PushCategoryToBagisto;
use Modules\Product\Models\Category;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BagistoServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bagisto';

    protected string $nameLower = 'bagisto';

    protected array $commands = [
        SyncExistingProductsCommand::class,
        SyncCategoriesCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** Bagisto'ya giden kategori alanları; yalnız bunlar değişince push edilir (deleted_at: geri yükleme yeniden aktifleştirir). */
    private const SYNCED_CATEGORY_FIELDS = ['name', 'slug', 'parent_id', 'status', 'sort_order', 'deleted_at'];

    public function boot(): void
    {
        parent::boot();

        // Kategori yazma yolları (liste ekranı, ürün formundan hızlı ekleme, toplu silme) domain
        // event'i yayınlamıyor; Product modülüne dokunmadan model olaylarına dışarıdan bağlanılır.
        // Önceden kategori yalnız bir ürünle birlikte Bagisto'da açılıyordu (2026-09-26).
        // created/updated ayrı: wasRecentlyCreated aynı model örneğindeki sonraki güncellemelerde de true kalır.
        Category::created(function (Category $category) {
            PushCategoryToBagisto::dispatch($category->id)->afterCommit();
        });

        Category::updated(function (Category $category) {
            if (! $category->wasChanged(self::SYNCED_CATEGORY_FIELDS)) {
                return;
            }

            PushCategoryToBagisto::dispatch($category->id, PushCategoryToBagisto::EVENT_UPSERTED, $category->getOriginal('slug'))
                ->afterCommit();
        });

        Category::deleted(function (Category $category) {
            PushCategoryToBagisto::dispatch($category->id, PushCategoryToBagisto::EVENT_DELETED)->afterCommit();
        });
    }
}
