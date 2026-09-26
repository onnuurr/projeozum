<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Category;

/**
 * SaaS kategorisini Bagisto'ya gönderir (Webkul\SaasSync CategoryWebhookController). Eşleşme slug
 * ile; slug değiştiyse eski slug da gider. Silinen kategori Bagisto'da pasife alınır.
 *
 * Kategori verisi handle() anında taze okunur (withTrashed: silme sonrası da slug gerekir).
 */
class PushCategoryToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const EVENT_UPSERTED = 'upserted';
    public const EVENT_DELETED = 'deleted';

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(
        protected int $categoryId,
        protected string $event = self::EVENT_UPSERTED,
        protected ?string $oldSlug = null,
    ) {}

    public function handle(BagistoSyncClient $client): void
    {
        $category = Category::withTrashed()->with(['parent' => fn ($q) => $q->withTrashed()])->find($this->categoryId);

        if (! $category) {
            return;
        }

        if ($this->event === self::EVENT_DELETED) {
            $client->post('api/saas-sync/categories', ['event' => self::EVENT_DELETED, 'slug' => $category->slug]);

            return;
        }

        $client->post('api/saas-sync/categories', [
            'event' => self::EVENT_UPSERTED,
            'slug' => $category->slug,
            'old_slug' => $this->oldSlug !== $category->slug ? $this->oldSlug : null,
            'name' => $category->name,
            'parent_slug' => $category->parent?->slug,
            'status' => $category->status === 'active' && ! $category->trashed(),
            'position' => (int) $category->sort_order,
        ]);
    }
}
