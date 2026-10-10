<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Brand;
use Modules\Product\Models\ProductAttributeOption;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;

/**
 * SaaS'taki renk/beden (Ürün Ayarları) ve marka (Markalar) kayıtlarını Bagisto'nun `color`/`size`/
 * `brand` attribute seçeneklerine, Ürün Ayarları → Özellikler seçeneklerini de (ProductAttributeOption)
 * kendi özelliğinin koduna gönderir (Webkul\SaasSync AttributeOptionWebhookController).
 * Eşleşme seçenek adıyla (ürün push'unun varyant rengini/bedenini ve markayı çözdüğü anahtar); ad
 * değiştiyse eski ad da gider ve Bagisto seçeneği yerinde yeniden adlandırır.
 *
 * Upsert'te kayıt handle() anında taze okunur: art arda iki yeniden adlandırmada geciken bir
 * retry eski adı bayat yeni adla eşlemesin. Silmede kayıt artık yok (hard delete) — ad
 * constructor'da taşınır.
 */
class PushAttributeOptionToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const EVENT_UPSERTED = 'upserted';
    public const EVENT_DELETED = 'deleted';

    /** Bagisto attribute kodu → SaaS modeli. */
    public const MODELS = [
        'color' => ProductColor::class,
        'size'  => ProductSize::class,
        'brand' => Brand::class,
    ];

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    /**
     * @param  string  $attribute  'color'|'size'|'brand' ya da bir ProductAttribute kodu
     */
    public function __construct(
        protected string $attribute,
        protected int $optionId,
        protected string $event = self::EVENT_UPSERTED,
        protected ?string $name = null,
        protected ?string $oldName = null,
    ) {}

    public static function forOption(ProductColor|ProductSize|Brand|ProductAttributeOption $option, string $event = self::EVENT_UPSERTED, ?string $oldName = null): self
    {
        $attribute = $option instanceof ProductAttributeOption
            ? $option->attribute()->value('code')
            : array_search($option::class, self::MODELS, true);

        return new self($attribute, $option->id, $event, $option->name, $oldName);
    }

    public function handle(BagistoSyncClient $client): void
    {
        if ($this->event === self::EVENT_DELETED) {
            $client->post('api/saas-sync/attribute-options', [
                'event' => self::EVENT_DELETED,
                'attribute' => $this->attribute,
                'name' => $this->name,
            ]);

            return;
        }

        $option = (self::MODELS[$this->attribute] ?? ProductAttributeOption::class)::find($this->optionId);

        // Kuyrukta beklerken silinmiş: silme job'ı ayrıca gönderiliyor.
        if (! $option) {
            return;
        }

        $client->post('api/saas-sync/attribute-options', [
            'event' => self::EVENT_UPSERTED,
            'attribute' => $this->attribute,
            'name' => $option->name,
            'old_name' => $this->oldName !== $option->name ? $this->oldName : null,
            'swatch' => $option instanceof ProductColor ? $option->hex : null,
            // Özellik seçeneğinin özel (oz:) ikonu → Bagisto seçenek görseli; Lucide ikonları dosya
            // olarak sunucuda yok, gönderilmez. Boş = görseli kaldır.
            ...($option instanceof ProductAttributeOption ? ['icon_svg' => $this->iconSvg($option->icon)] : []),
            'position' => (int) $option->sort_order,
        ]);
    }

    /** resources/icons/custom/<ad>.svg içeriği ('oz:<ad>' için); yoksa null. */
    private function iconSvg(?string $icon): ?string
    {
        if (! $icon || ! str_starts_with($icon, 'oz:')) {
            return null;
        }

        $path = resource_path('icons/custom/'.basename(substr($icon, 3)).'.svg');

        return is_file($path) ? file_get_contents($path) : null;
    }
}
