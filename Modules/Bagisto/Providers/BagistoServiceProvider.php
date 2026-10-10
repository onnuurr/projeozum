<?php

namespace Modules\Bagisto\Providers;

use App\Contracts\TranslationSource;
use Modules\Bagisto\Console\SyncCategoriesCommand;
use Modules\Bagisto\Console\SyncExistingProductsCommand;
use Modules\Bagisto\Console\SyncAttributeOptionsCommand;
use Modules\Bagisto\Jobs\PushCategoryToBagisto;
use Modules\Bagisto\Jobs\PushAttributeOptionToBagisto;
use Modules\Bagisto\Jobs\PushProductAttributeToBagisto;
use Modules\Bagisto\Services\BagistoTranslationSource;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductAttributeOption;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Models\Product;
use Modules\Product\Services\VatRateService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BagistoServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bagisto';

    protected string $nameLower = 'bagisto';

    protected array $commands = [
        SyncExistingProductsCommand::class,
        SyncCategoriesCommand::class,
        SyncAttributeOptionsCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** Bagisto'ya giden kategori alanları; yalnız bunlar değişince push edilir (deleted_at: geri yükleme yeniden aktifleştirir). */
    private const SYNCED_CATEGORY_FIELDS = ['name', 'slug', 'parent_id', 'status', 'sort_order', 'deleted_at'];

    public function register(): void
    {
        parent::register();

        // Süper Admin › Dil Dosyaları'na Bagisto dil dosyalarını kaynak olarak ekler (bkz. App\Contracts\TranslationSource).
        $this->app->tag([BagistoTranslationSource::class], TranslationSource::TAG);
    }

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

        // KDV oranı değişince kategori (ve alt kategorileri) ürünlerinin mağaza fiyatı değişir → yeniden gönder.
        Category::updated(function (Category $category) {
            if (! $category->wasChanged('vat_rate')) {
                return;
            }

            app(VatRateService::class)->flush();
            $ids = [$category->id];
            for ($level = $ids; $level !== [];) {
                $level = Category::whereIn('parent_id', $level)->pluck('id')->all();
                $ids = array_merge($ids, $level);
            }

            Product::whereIn('category_id', $ids)->where('status', '!=', Product::STATUS_DEVELOPMENT)->pluck('id')
                ->each(fn (int $id) => PushProductToBagisto::dispatch($id, 'updated')->afterCommit());
        });

        Category::deleted(function (Category $category) {
            PushCategoryToBagisto::dispatch($category->id, PushCategoryToBagisto::EVENT_DELETED)->afterCommit();
        });

        // Renk/beden (Ürün Ayarları) ve markalar Bagisto color/size/brand seçeneklerinin ana kaynağı;
        // kategorilerle aynı desen: model olaylarına dışarıdan bağlanılır.
        // Sistem özelliklerinin (KDV) seçenekleri Bagisto'ya attribute seçeneği olarak gitmez.
        $isSystemOption = fn ($option) => $option instanceof ProductAttributeOption && $option->attribute?->isSystem();

        foreach ([ProductColor::class, ProductSize::class, Brand::class, ProductAttributeOption::class] as $optionModel) {
            $optionModel::created(function ($option) use ($isSystemOption) {
                if (! $isSystemOption($option)) {
                    dispatch(PushAttributeOptionToBagisto::forOption($option))->afterCommit();
                }
            });

            $optionModel::updated(function ($option) use ($isSystemOption) {
                if (! $option->wasChanged(['name', 'hex', 'sort_order', 'icon']) || $isSystemOption($option)) {
                    return;
                }

                dispatch(PushAttributeOptionToBagisto::forOption($option, PushAttributeOptionToBagisto::EVENT_UPSERTED, $option->getOriginal('name')))
                    ->afterCommit();
            });

            $optionModel::deleted(function ($option) use ($isSystemOption) {
                if (! $isSystemOption($option)) {
                    dispatch(PushAttributeOptionToBagisto::forOption($option, PushAttributeOptionToBagisto::EVENT_DELETED))->afterCommit();
                }
            });
        }

        // KDV seçeneğinin oranı (adı) değişince onu seçmiş ürünlerin mağaza fiyatı değişir → yeniden gönder.
        ProductAttributeOption::updated(function (ProductAttributeOption $option) {
            if (! $option->wasChanged('name') || $option->attribute?->code !== ProductAttribute::VAT_CODE) {
                return;
            }

            app(VatRateService::class)->flush();
            Product::whereNotNull('attributes')->where('status', '!=', Product::STATUS_DEVELOPMENT)->get(['id', 'attributes'])
                ->filter(fn (Product $p) => (int) (($p->attributes ?? [])[ProductAttribute::VAT_CODE] ?? 0) === $option->id)
                ->each(fn (Product $p) => PushProductToBagisto::dispatch($p->id, 'updated')->afterCommit());
        });

        // Ürün Ayarları → Özellikler tanımları Bagisto attribute'larının ana kaynağı (seçenekleri yukarıda).
        // Silinen özelliğin seçenekleri DB cascade ile gider (olay yok); Bagisto'da da attribute ile silinir.
        // Sistem özellikleri (KDV) mağazada attribute değildir.
        ProductAttribute::created(function (ProductAttribute $a) {
            if (! $a->isSystem()) {
                dispatch(PushProductAttributeToBagisto::forAttribute($a))->afterCommit();
            }
        });

        ProductAttribute::updated(function (ProductAttribute $a) {
            if ($a->wasChanged(['name', 'is_filterable', 'sort_order']) && ! $a->isSystem()) {
                dispatch(PushProductAttributeToBagisto::forAttribute($a))->afterCommit();
            }
        });

        ProductAttribute::deleted(function (ProductAttribute $a) {
            if (! $a->isSystem()) {
                dispatch(PushProductAttributeToBagisto::forAttribute($a, PushProductAttributeToBagisto::EVENT_DELETED))->afterCommit();
            }
        });
    }
}
