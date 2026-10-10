<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Bagisto\Jobs\PushAttributeOptionToBagisto;
use Modules\Bagisto\Jobs\PushProductAttributeToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün Ayarları → Özellikler: global özellik + seçenek CRUD'u (product-attribute.*), kategori
 * ataması (üstten miras), ürün kaydında dinamik doğrulama/normalizasyon ve Bagisto senkronu.
 */
class ProductAttributeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Genel özellik davranışı boş tabloyla sınanır; migration'ların açtığı `mensei`, `cinsiyet` ve
        // `kdv_orani` için MenseiAttributeTest / GenderAttributeTest / ProductPriceTabTest.
        ProductAttribute::whereIn('code', ['mensei', 'cinsiyet', ProductAttribute::VAT_CODE])->delete();
    }

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    private function category(?int $parentId = null): Category
    {
        return Category::create([
            'name' => 'Kategori '.uniqid(), 'slug' => 'kat-'.uniqid(),
            'parent_id' => $parentId, 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    private function collar(): ProductAttribute
    {
        $this->actingAs($this->user(['product-attribute.create']))->post('/products/settings/attributes', [
            'name' => 'Yaka Tipi', 'code' => 'yaka_tipi', 'type' => 'select',
            'options' => [['name' => 'V Yaka'], ['name' => 'Bisiklet Yaka']],
        ])->assertRedirect('/products/settings?tab=attributes');

        return ProductAttribute::where('code', 'yaka_tipi')->firstOrFail();
    }

    private function productIn(Category $category, string $sku): Product
    {
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => $sku, 'price' => 100,
        ]);
        $product->variants()->create(['sku' => "{$sku}-M", 'price' => 100, 'stock' => 1, 'size' => 'M']);

        return $product;
    }

    private function updatePayload(Category $category, string $sku, array $attributes): array
    {
        return [
            'name' => 'Ürün', 'sku' => $sku, 'category_id' => $category->id, 
            'variants' => [['sku' => "{$sku}-M", 'price' => 100, 'stock' => 1, 'size' => 'M']],
            'attributes' => $attributes,
        ];
    }

    public function test_each_action_requires_its_own_permission(): void
    {
        $payload = ['name' => 'Kumaş', 'code' => 'kumas', 'type' => 'text'];
        $this->actingAs($this->user(['product-attribute.view']))->post('/products/settings/attributes', $payload)->assertForbidden();

        $attribute = $this->collar();
        $this->actingAs($this->user(['product-attribute.create']))->put("/products/settings/attributes/{$attribute->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->user(['product-attribute.update']))->delete("/products/settings/attributes/{$attribute->id}")->assertForbidden();

        // Ayar sayfası: sekme verisi yalnız görme yetkisiyle gelir; tek başına yeterli sekme yetkisi
        $this->withoutVite();
        $this->actingAs($this->user(['product-settings.view', 'product-attribute.view']))->get('/products/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sizes', null)->has('attributes', 1)->where('attributes.0.code', 'yaka_tipi'));
    }

    public function test_create_validates_code_and_options(): void
    {
        $user = $this->user(['product-attribute.create']);

        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Renk 2', 'code' => 'color', 'type' => 'text'])
            ->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Yaka', 'code' => 'Yaka-Tipi', 'type' => 'text'])
            ->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Yaka', 'code' => 'yaka', 'type' => 'select', 'options' => []])
            ->assertSessionHasErrors('options');
        $this->actingAs($user)->post('/products/settings/attributes', [
            'name' => 'Yaka', 'code' => 'yaka', 'type' => 'select', 'options' => [['name' => 'V'], ['name' => 'v']],
        ])->assertSessionHasErrors('options.1.name');

        $this->assertSame(0, ProductAttribute::count());
    }

    public function test_duplicate_attribute_and_option_names_are_rejected(): void
    {
        $attribute = $this->collar(); // "Yaka Tipi" / yaka_tipi, seçenekler: V Yaka, Bisiklet Yaka
        $creator = $this->user(['product-attribute.create']);

        // Ad: harf, Türkçe İ/ı ve boşluk farkı mükerrer sayılır; kod zaten tekil
        foreach (['Yaka Tipi', 'yaka tipi', 'YAKA TİPİ', '  Yaka   Tipi '] as $name) {
            $this->actingAs($creator)->post('/products/settings/attributes', ['name' => $name, 'code' => 'yaka_2', 'type' => 'text'])
                ->assertSessionHasErrors('name');
        }
        $this->actingAs($creator)->post('/products/settings/attributes', ['name' => 'Başka', 'code' => 'Yaka_Tipi', 'type' => 'text'])
            ->assertSessionHasErrors('code');

        // Seçenek: aynı listede Türkçe harf farkıyla tekrar
        $this->actingAs($creator)->post('/products/settings/attributes', [
            'name' => 'Kumaş', 'code' => 'kumas', 'type' => 'select', 'options' => [['name' => 'İpek'], ['name' => 'ipek']],
        ])->assertSessionHasErrors('options.1.name');
        $this->assertSame(1, ProductAttribute::count());

        // Güncelleme: kendi adı serbest, başka özelliğin adı yasak
        $editor = $this->user(['product-attribute.update']);
        $this->actingAs($creator)->post('/products/settings/attributes', ['name' => 'Sezon', 'code' => 'sezon', 'type' => 'text'])->assertRedirect();
        [$v, $crew] = $attribute->options->all();
        $keep = [['id' => $v->id, 'name' => 'V Yaka'], ['id' => $crew->id, 'name' => 'Bisiklet Yaka']];

        $this->actingAs($editor)->put("/products/settings/attributes/{$attribute->id}", ['name' => 'yaka tipi', 'options' => $keep])
            ->assertSessionHasNoErrors();
        $this->actingAs($editor)->put("/products/settings/attributes/{$attribute->id}", ['name' => 'SEZON', 'options' => $keep])
            ->assertSessionHasErrors('name');

        // Takas (V Yaka↔Bisiklet Yaka) ve tutulan seçeneğin adını yeni seçeneğe verme reddedilir
        $this->actingAs($editor)->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka Tipi', 'options' => [['id' => $v->id, 'name' => 'Bisiklet Yaka'], ['id' => $crew->id, 'name' => 'V Yaka']],
        ])->assertSessionHasErrors('options.0.name');
        $this->actingAs($editor)->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka Tipi', 'options' => [...$keep, ['name' => 'v yaka']],
        ])->assertSessionHasErrors('options.2.name');

        // Silinen seçeneğin adı yeni seçenekte yeniden kullanılabilir
        $this->actingAs($editor)->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka Tipi', 'options' => [['id' => $crew->id, 'name' => 'Bisiklet Yaka'], ['name' => 'V Yaka']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Bisiklet Yaka', 'V Yaka'], $attribute->options()->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_update_keeps_code_and_type_syncs_options(): void
    {
        $attribute = $this->collar();
        [$v, $crew] = $attribute->options->all();

        $this->actingAs($this->user(['product-attribute.update']))->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka', 'code' => 'baska', 'type' => 'text',
            'options' => [['id' => $crew->id, 'name' => 'Bisiklet'], ['name' => 'Polo Yaka']],
        ])->assertRedirect();

        $attribute->refresh();
        $this->assertSame(['yaka_tipi', 'select', 'Yaka'], [$attribute->code, $attribute->type, $attribute->name]);
        $this->assertSame(['Bisiklet', 'Polo Yaka'], $attribute->options->pluck('name')->all());
        $this->assertSame($crew->id, $attribute->options->first()->id); // yeniden adlandırma id'yi korur
        $this->assertDatabaseMissing('product_attribute_options', ['id' => $v->id]);
    }

    public function test_used_attribute_and_option_cannot_be_deleted(): void
    {
        $attribute = $this->collar();
        $v = $attribute->options->first();
        $category = $this->category();
        $category->productAttributes()->attach($attribute->id);
        $product = $this->productIn($category, 'ATT-U');
        $product->update(['attributes' => ['yaka_tipi' => $v->id]]);

        $this->actingAs($this->user(['product-attribute.delete']))->delete("/products/settings/attributes/{$attribute->id}")
            ->assertSessionHasErrors('attribute');
        $this->actingAs($this->user(['product-attribute.update']))->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka Tipi', 'options' => [['name' => 'Bisiklet Yaka']],
        ])->assertSessionHasErrors('options');

        $this->assertSame(2, $attribute->options()->count());
    }

    public function test_category_assignment_is_inherited_and_validated_on_product_save(): void
    {
        $attribute = $this->collar();
        $parent = $this->category();
        $child = $this->category($parent->id);

        $this->actingAs($this->user(['category.view']))->put("/products/categories/{$parent->id}/attributes", ['attributes' => []])
            ->assertForbidden();
        $this->actingAs($this->user(['category.manage']))->put("/products/categories/{$parent->id}/attributes", [
            'attributes' => [['id' => $attribute->id, 'required' => true]],
        ])->assertRedirect();

        // Alt kategorideki ürün üst kategorinin zorunlu özelliğini görür
        $product = $this->productIn($child, 'ATT-1');
        $editor = $this->user(['product.add']);

        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($child, 'ATT-1', []))
            ->assertSessionHasErrors('attributes.yaka_tipi');
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($child, 'ATT-1', ['yaka_tipi' => 999999]))
            ->assertSessionHasErrors('attributes.yaka_tipi');

        $v = $attribute->options->first();
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($child, 'ATT-1', [
            'yaka_tipi' => $v->id, 'tanimsiz' => 'silinmeli',
        ]))->assertRedirect();

        $this->assertSame(['yaka_tipi' => $v->id], $product->fresh()->attributes);
    }

    public function test_multiselect_text_and_boolean_values_are_normalized(): void
    {
        $user = $this->user(['product-attribute.create']);
        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Sezon', 'code' => 'sezon', 'type' => 'multiselect', 'options' => [['name' => 'Yaz'], ['name' => 'Kış']]]);
        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Kumaş', 'code' => 'kumas', 'type' => 'text']);
        $this->actingAs($user)->post('/products/settings/attributes', ['name' => 'Organik', 'code' => 'organik', 'type' => 'boolean']);

        $category = $this->category();
        $this->actingAs($this->user(['category.manage']))->put("/products/categories/{$category->id}/attributes", [
            'attributes' => ProductAttribute::pluck('id')->map(fn ($id) => ['id' => $id])->all(),
        ]);

        $season = ProductAttribute::where('code', 'sezon')->first()->options;
        $product = $this->productIn($category, 'ATT-2');
        $this->actingAs($this->user(['product.add']))->put(route('products.update', $product), $this->updatePayload($category, 'ATT-2', [
            'sezon' => [$season[0]->id, $season[1]->id], 'kumas' => '  %100 Pamuk ', 'organik' => true,
        ]))->assertRedirect();

        $product->refresh();
        $this->assertSame(['sezon' => [$season[0]->id, $season[1]->id], 'kumas' => '%100 Pamuk', 'organik' => true], $product->attributes);

        // Bagisto payload'u: adlarla, tüm kodlar
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
        $this->assertSame(['sezon' => ['Yaz', 'Kış'], 'kumas' => '%100 Pamuk', 'organik' => true], $payload['attributes']);

        // Detay sayfası Özellikler tablosu: Bagisto'daki gibi tüm kategori özellikleri, adlarıyla
        $this->actingAs($this->user(['product.view']))->withoutVite()
            ->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.specs.Sezon', 'Yaz, Kış')
                ->where('product.specs.Kumaş', '%100 Pamuk')
                ->where('product.specs.Organik', 'Evet'));
    }

    public function test_product_dimensions_are_saved(): void
    {
        $category = $this->category();
        $product = $this->productIn($category, 'DIM-1');

        $this->actingAs($this->user(['product.add']))->put(route('products.update', $product), [
            ...$this->updatePayload($category, 'DIM-1', []),
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'desi' => 2,
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(['30.00', '20.00', '10.00', '2.00'], [$product->length_cm, $product->width_cm, $product->height_cm, $product->desi]);

        $this->actingAs($this->user(['product.add']))->put(route('products.update', $product), [
            ...$this->updatePayload($category, 'DIM-1', []), 'length_cm' => -1,
        ])->assertSessionHasErrors('length_cm');
    }

    public function test_extended_types_are_validated_normalized_and_mapped(): void
    {
        $user = $this->user(['product-attribute.create']);
        foreach ([
            ['name' => 'Açıklama Notu', 'code' => 'not', 'type' => 'textarea'],
            ['name' => 'Göğüs', 'code' => 'gogus', 'type' => 'number'],
            ['name' => 'Etiket Fiyatı', 'code' => 'etiket_fiyati', 'type' => 'price'],
            ['name' => 'Sezon Başı', 'code' => 'sezon_basi', 'type' => 'date'],
            ['name' => 'Lansman', 'code' => 'lansman', 'type' => 'datetime'],
            ['name' => 'Kullanım', 'code' => 'kullanim', 'type' => 'checkbox', 'options' => [['name' => 'Günlük'], ['name' => 'Spor']]],
        ] as $payload) {
            $this->actingAs($user)->post('/products/settings/attributes', $payload)->assertSessionHasNoErrors();
        }
        // Metin tipinde filtre açılamaz; fiyat filtrelenebilir
        $this->assertFalse(ProductAttribute::where('code', 'not')->value('is_filterable'));
        $this->assertTrue(ProductAttribute::where('code', 'etiket_fiyati')->value('is_filterable'));

        $category = $this->category();
        $category->productAttributes()->attach(ProductAttribute::pluck('id'));
        $product = $this->productIn($category, 'EXT-1');
        $editor = $this->user(['product.add']);

        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($category, 'EXT-1', [
            'gogus' => 'abc', 'sezon_basi' => '03.10.2026',
        ]))->assertSessionHasErrors(['attributes.gogus', 'attributes.sezon_basi']);

        $use = ProductAttribute::where('code', 'kullanim')->first()->options;
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($category, 'EXT-1', [
            'not' => ' Uzun not ', 'gogus' => '52.5', 'etiket_fiyati' => '199.999', 'sezon_basi' => '2026-10-03',
            'lansman' => '2026-10-03T14:30', 'kullanim' => [$use[1]->id],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([
            'not' => 'Uzun not', 'gogus' => 52.5, 'etiket_fiyati' => 200, 'sezon_basi' => '2026-10-03', // fiyat jsonb'de 200.0 → 200
            'lansman' => '2026-10-03 14:30:00', 'kullanim' => [$use[1]->id],
        ], $product->fresh()->attributes);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
        $this->assertSame(['Spor'], $payload['attributes']['kullanim']);
        $this->assertSame('2026-10-03 14:30:00', $payload['attributes']['lansman']);
    }

    public function test_image_attribute_upload_replace_and_remove(): void
    {
        Storage::fake(Media::disk());
        $this->actingAs($this->user(['product-attribute.create']))->post('/products/settings/attributes', ['name' => 'Beden Tablosu', 'code' => 'beden_tablosu', 'type' => 'image']);
        $category = $this->category();
        $category->productAttributes()->attach(ProductAttribute::pluck('id'));
        $product = $this->productIn($category, 'IMG-1');
        $editor = $this->user(['product.add']);

        $this->actingAs($editor)->post("/products/{$product->id}", ['_method' => 'put', ...$this->updatePayload($category, 'IMG-1', [
            'beden_tablosu' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml'),
        ])])->assertSessionHasErrors('attributes.beden_tablosu');
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($category, 'IMG-1', [
            'beden_tablosu' => '../../.env',
        ]))->assertSessionHasErrors('attributes.beden_tablosu');

        $this->actingAs($editor)->post("/products/{$product->id}", ['_method' => 'put', ...$this->updatePayload($category, 'IMG-1', [
            'beden_tablosu' => UploadedFile::fake()->image('tablo.png'),
        ])])->assertSessionHasNoErrors();
        $first = $product->fresh()->attributes['beden_tablosu'];
        Storage::disk(Media::disk())->assertExists($first);

        // Değer olduğu gibi geri gönderilince dosya korunur
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($category, 'IMG-1', ['beden_tablosu' => $first]))->assertSessionHasNoErrors();
        Storage::disk(Media::disk())->assertExists($first);
        $this->assertSame(Media::url($first), app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated')['attributes']['beden_tablosu']);

        // Kaldırılınca dosya silinir
        $this->actingAs($editor)->put(route('products.update', $product), $this->updatePayload($category, 'IMG-1', []))->assertSessionHasNoErrors();
        $this->assertSame([], $product->fresh()->attributes);
        Storage::disk(Media::disk())->assertMissing($first);
    }

    public function test_payload_carries_featured_and_production_fields(): void
    {
        $category = $this->category();
        $product = $this->productIn($category, 'FEAT-1');
        $product->update(['is_featured' => true, 'material' => '%100 Pamuk']);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');

        $this->assertSame([true, '%100 Pamuk'], [$payload['featured'], $payload['material']]);
        // Bakım talimatı kaldırıldı (yıkama talimatı kategori özelliği)
        $this->assertArrayNotHasKey('care_instructions', $payload);
        // Menşei artık sabit alan değil, `mensei` özelliği olarak gider
        $this->assertArrayNotHasKey('origin_country', $payload);
    }

    public function test_option_icons_are_saved_validated_and_sent_as_svg(): void
    {
        Queue::fake();
        $user = $this->user(['product-attribute.create', 'product-attribute.update']);

        $this->actingAs($user)->post('/products/settings/attributes', [
            'name' => 'Bakım', 'code' => 'bakim', 'type' => 'checkbox',
            'options' => [['name' => '30°C yıkama', 'icon' => '<svg>'], ['name' => 'Ütülenmez']],
        ])->assertSessionHasErrors('options.0.icon');

        $this->actingAs($user)->post('/products/settings/attributes', [
            'name' => 'Bakım', 'code' => 'bakim', 'type' => 'checkbox',
            'options' => [['name' => '30°C yıkama', 'icon' => 'oz:bakim-yikama-30'], ['name' => 'Ütülenmez', 'icon' => 'shirt']],
        ])->assertSessionHasNoErrors();

        [$wash, $iron] = ProductAttribute::where('code', 'bakim')->first()->options->all();
        $this->assertSame(['oz:bakim-yikama-30', 'shirt'], [$wash->icon, $iron->icon]);

        $payloadOf = function ($option) {
            $job = \Modules\Bagisto\Jobs\PushAttributeOptionToBagisto::forOption($option);
            $method = new \ReflectionMethod($job, 'iconSvg');

            return $method->invoke($job, $option->icon);
        };
        // Özel (oz:) ikon dosyadan SVG olarak gider; Lucide ikonu sunucuda dosya olmadığı için gitmez
        $this->assertStringStartsWith('<svg', $payloadOf($wash));
        $this->assertStringContainsString('30°C yıkama', $payloadOf($wash));
        $this->assertNull($payloadOf($iron));

        // İkon değişimi Bagisto'ya yeniden gönderilir
        $before = Queue::pushed(PushAttributeOptionToBagisto::class)->count();
        $this->actingAs($user)->put("/products/settings/attributes/{$wash->attribute->id}", [
            'name' => 'Bakım', 'options' => [['id' => $wash->id, 'name' => '30°C yıkama', 'icon' => 'oz:bakim-yikama-40'], ['id' => $iron->id, 'name' => 'Ütülenmez', 'icon' => 'shirt']],
        ])->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Queue::pushed(PushAttributeOptionToBagisto::class)->count());
    }

    public function test_bagisto_sync_jobs_are_dispatched(): void
    {
        Queue::fake();

        $attribute = $this->collar();
        Queue::assertPushed(PushProductAttributeToBagisto::class, 1);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 2);

        $crew = $attribute->options->last();
        $this->actingAs($this->user(['product-attribute.update']))->put("/products/settings/attributes/{$attribute->id}", [
            'name' => 'Yaka Tipi', 'options' => [['id' => $crew->id, 'name' => 'Bisiklet Yaka']],
        ]);
        // ad değişmedi → özellik push'u yok; V Yaka silindi (1) + Bisiklet Yaka'nın sırası 2→1 (1)
        Queue::assertPushed(PushProductAttributeToBagisto::class, 1);
        Queue::assertPushed(PushAttributeOptionToBagisto::class, 4);

        $this->actingAs($this->user(['product-attribute.delete']))->delete("/products/settings/attributes/{$attribute->id}")->assertRedirect();
        Queue::assertPushed(PushProductAttributeToBagisto::class, 2);
    }
}
