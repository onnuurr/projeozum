<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushAttributeOptionToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Product\Models\ProductVariant;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün Ayarları'ndaki renk/beden seçenekleri ve markalar Bagisto color/size/brand seçeneklerinin
 * ana kaynağı: ekleme/düzenleme/silme push edilir, renk/beden yeniden adlandırması varyantlara da
 * yansır. Ürün payload'ı marka/SEO/"Yeni" alanlarını taşır.
 */
class AttributeOptionSyncTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        foreach (['variant-option.create', 'variant-option.update', 'variant-option.delete'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    private function productWithVariant(array $variant): Product
    {
        $category = Category::create(['name' => 'Elbise', 'slug' => 'elbise-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Elbise', 'sku' => 'VO-'.uniqid(), 'price' => 100,
        ]);
        $product->variants()->create(['sku' => $product->sku.'-1', 'price' => 100, 'stock' => 0] + $variant);

        return $product;
    }

    public function test_create_update_delete_queue_pushes(): void
    {
        Queue::fake();
        $user = $this->user();

        $this->actingAs($user)->post('/products/settings/colors', ['name' => 'Haki', 'hex' => '#6b7a3a'])->assertRedirect();
        $color = ProductColor::where('name', 'Haki')->firstOrFail();
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 1);

        // Değişmeyen kayıt push üretmez
        $this->actingAs($user)->put("/products/settings/colors/{$color->id}", ['name' => 'Haki', 'hex' => '#6b7a3a', 'sort_order' => $color->sort_order]);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 1);

        $this->actingAs($user)->put("/products/settings/colors/{$color->id}", ['name' => 'Asker Yeşili', 'hex' => '#6b7a3a']);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 2);

        $this->actingAs($user)->delete("/products/settings/colors/{$color->id}");
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 3);
    }

    public function test_bulk_delete_pushes_each_option(): void
    {
        Queue::fake();
        $ids = ProductSize::orderBy('sort_order')->limit(3)->pluck('id')->all();

        $this->actingAs($this->user())->post('/products/settings/sizes/bulk-destroy', ['ids' => $ids])->assertRedirect();

        $this->assertSame(0, ProductSize::whereIn('id', $ids)->count());
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 3);
    }

    public function test_rename_propagates_to_variants_and_fires_product_updated(): void
    {
        Queue::fake();
        $color = ProductColor::where('name', 'Mavi')->firstOrFail();
        $product = $this->productWithVariant(['size' => 'M', 'color_name' => 'Mavi', 'color_hex' => $color->hex]);
        $other = $this->productWithVariant(['size' => 'M', 'color_name' => 'Siyah', 'color_hex' => '#111111']);

        Event::fake([ProductUpdated::class]);

        $this->actingAs($this->user())->put("/products/settings/colors/{$color->id}", ['name' => 'Saks Mavi', 'hex' => '#1d4ed8'])->assertRedirect();

        $variant = ProductVariant::where('product_id', $product->id)->first();
        $this->assertSame('Saks Mavi', $variant->color_name);
        $this->assertSame('#1d4ed8', $variant->color_hex);
        $this->assertSame('Siyah', ProductVariant::where('product_id', $other->id)->value('color_name'));

        Event::assertDispatched(ProductUpdated::class, fn ($e) => $e->product->id === $product->id);
        Event::assertDispatchedTimes(ProductUpdated::class, 1);

        $size = ProductSize::where('name', 'M')->firstOrFail();
        $this->actingAs($this->user())->put("/products/settings/sizes/{$size->id}", ['name' => 'Medium'])->assertRedirect();
        $this->assertSame(2, ProductVariant::where('size', 'Medium')->count());
    }

    public function test_push_payloads(): void
    {
        config(['bagisto.sync.base_url' => 'https://bg.example.com', 'bagisto.sync.webhook_secret' => 'secret']);
        Http::fake(['bg.example.com/*' => Http::response(['data' => []])]);
        Queue::fake();
        $client = app(BagistoSyncClient::class);

        $color = ProductColor::where('name', 'Mavi')->firstOrFail();
        $color->update(['name' => 'Saks Mavi']);
        (new PushAttributeOptionToBagisto('color', $color->id, PushAttributeOptionToBagisto::EVENT_UPSERTED, 'Saks Mavi', 'Mavi'))->handle($client);

        Http::assertSent(fn ($r) => $r->url() === 'https://bg.example.com/api/saas-sync/attribute-options'
            && $r['event'] === 'upserted' && $r['attribute'] === 'color' && $r['name'] === 'Saks Mavi'
            && $r['old_name'] === 'Mavi' && $r['swatch'] === $color->hex && $r['position'] === $color->sort_order
            && $r->hasHeader('X-Saas-Signature'));

        $size = ProductSize::where('name', 'XL')->firstOrFail();
        PushAttributeOptionToBagisto::forOption($size)->handle($client);
        Http::assertSent(fn ($r) => $r['attribute'] === 'size' && $r['name'] === 'XL' && $r['old_name'] === null && $r['swatch'] === null);

        $size->delete();
        PushAttributeOptionToBagisto::forOption($size, PushAttributeOptionToBagisto::EVENT_DELETED)->handle($client);
        Http::assertSent(fn ($r) => $r['event'] === 'deleted' && $r['attribute'] === 'size' && $r['name'] === 'XL');
    }

    public function test_brand_changes_queue_pushes_with_old_name(): void
    {
        Queue::fake();

        $brand = Brand::create(['name' => 'Özüm', 'slug' => 'ozum', 'sort_order' => 1]);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 1);

        $brand->update(['slug' => 'ozum-2']); // Bagisto'ya gitmeyen alan
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 1);

        $brand->update(['name' => 'Özüm Kids']);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, fn ($job) => $this->jobProp($job, 'attribute') === 'brand'
            && $this->jobProp($job, 'oldName') === 'Özüm');

        $brand->delete();
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 3);
    }

    public function test_product_payload_carries_brand_seo_new_flag_barcode_and_special_price(): void
    {
        Queue::fake();
        $brand = Brand::create(['name' => 'Özüm', 'slug' => 'ozum', 'sort_order' => 1]);
        $product = $this->productWithVariant(['size' => 'M']);
        $product->update(['brand_id' => $brand->id, 'is_new' => true, 'meta_title' => 'Elbise | Özüm']);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh()->load('variants.images', 'images', 'category', 'brand'), 'updated');

        $this->assertSame('Özüm', $payload['brand']);
        $this->assertTrue($payload['new']);
        $this->assertSame('Elbise | Özüm', $payload['meta_title']);
        $this->assertSame('', $payload['meta_description'], 'boş alan Bagisto\'da temizlensin diye boş string gider');

        $this->assertSame('', $payload['product_number']);

        $product->update(['brand_id' => null, 'barcode' => '8680000000017']);
        $product->variants()->first()->update(['price' => 80, 'old_price' => 100]);
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh()->load('variants.images', 'images', 'category', 'brand'), 'updated');
        $this->assertSame('', $payload['brand']);
        $this->assertSame('8680000000017', $payload['product_number']);
        // SaaS: price=güncel, old_price=üstü çizili → Bagisto: price=normal, special_price=indirimli (KDV dahil, %10)
        $this->assertSame(110.0, $payload['variants'][0]['price']);
        $this->assertSame(88.0, $payload['variants'][0]['special_price']);

        $product->variants()->first()->update(['price' => 120, 'old_price' => 100]);
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh()->load('variants.images', 'images', 'category', 'brand'), 'updated');
        $this->assertSame(132.0, $payload['variants'][0]['price']);
        $this->assertSame('', $payload['variants'][0]['special_price'], 'eski fiyat güncelden büyük değilse indirim yok');
    }

    private function jobProp(object $job, string $prop): mixed
    {
        return (fn () => $this->{$prop})->call($job);
    }
}
