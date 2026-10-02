<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushCategoryToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * SaaS kategorileri tek başına da Bagisto'ya gider (önceden yalnız ürünle birlikte açılıyordu),
 * ürün push'u öznitelik ailesini açıkça taşır (Bagisto'da 'default' aile yoktu → her ürün 500).
 */
class CategorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_updating_and_deleting_category_queues_push(): void
    {
        Queue::fake();

        // Ürünsüz kategori otomatik pasif (CategoryStatusService) — 'active' verilse durum değişimi de push üretirdi
        $category = Category::create(['name' => 'Bebek', 'slug' => 'bebek', 'status' => 'passive', 'sort_order' => 1]);
        Queue::assertPushed(PushCategoryToBagisto::class, 1);

        $category->update(['icon' => 'baby']); // Bagisto'ya gitmeyen alan
        Queue::assertPushed(PushCategoryToBagisto::class, 1);

        $category->update(['slug' => 'bebek-giyim']);
        Queue::assertPushed(PushCategoryToBagisto::class, 2);

        $category->delete();
        Queue::assertPushed(PushCategoryToBagisto::class, 3);
    }

    public function test_push_sends_slug_parent_old_slug_and_status(): void
    {
        config(['bagisto.sync.base_url' => 'https://bg.example.com', 'bagisto.sync.webhook_secret' => 'secret']);
        Http::fake(['bg.example.com/*' => Http::response(['data' => []])]);

        Queue::fake();
        $parent = Category::create(['name' => 'Bebek', 'slug' => 'bebek', 'status' => 'active']);
        $child = Category::create(['name' => 'Tulum', 'slug' => 'tulum', 'parent_id' => $parent->id, 'status' => 'passive', 'sort_order' => 3]);

        (new PushCategoryToBagisto($child->id, PushCategoryToBagisto::EVENT_UPSERTED, 'eski-tulum'))->handle(app(\Modules\Bagisto\Services\BagistoSyncClient::class));

        Http::assertSent(fn ($r) => $r->url() === 'https://bg.example.com/api/saas-sync/categories'
            && $r['event'] === 'upserted' && $r['slug'] === 'tulum' && $r['old_slug'] === 'eski-tulum'
            && $r['parent_slug'] === 'bebek' && $r['status'] === false && $r['position'] === 3
            && $r->hasHeader('X-Saas-Signature'));

        $child->delete();
        (new PushCategoryToBagisto($child->id, PushCategoryToBagisto::EVENT_DELETED))->handle(app(\Modules\Bagisto\Services\BagistoSyncClient::class));

        Http::assertSent(fn ($r) => $r['event'] === 'deleted' && $r['slug'] === 'tulum');
    }

    public function test_product_payload_carries_attribute_family(): void
    {
        config(['bagisto.sync.attribute_family_code' => 'kids']);
        Queue::fake();

        $product = Product::factory()->create();
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->load('variants.images', 'images', 'category'), 'created');

        $this->assertSame('kids', $payload['attribute_family_code']);
    }
}
