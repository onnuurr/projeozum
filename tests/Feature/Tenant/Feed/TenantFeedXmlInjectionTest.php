<?php

namespace Tests\Feature\Tenant\Feed;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * TenantFeedController ürün adını/marka adını CDATA içine ham (escape'siz) yazıyordu:
 * '<g:title><![CDATA[' . $p->name . ']]></g:title>'. Ürün adı içine literal ']]>'
 * dizisi konursa CDATA bloğu erken kapanır ve ondan sonraki metin gerçek XML/markup
 * olarak parse edilir — pazaryeri feed tüketicisine (ya da feed'i tarayıcıda açan
 * birine) enjekte edilebilir XML/HTML anlamına gelir. Bu test hem üretilen feed'in
 * geçerli XML kalmasını hem de enjekte edilmeye çalışılan etiketin literal metin
 * olarak kaldığını (gerçek bir <item> ya da <script> etiketine dönüşmediğini) doğrular.
 */
class TenantFeedXmlInjectionTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name, ?int $brandId = null): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'name' => $name, 'slug' => 'p-' . uniqid(),
            'sku' => 'SKU-' . uniqid(), 'price' => 100,
            'brand_id' => $brandId, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_product_name_cannot_break_out_of_cdata_and_inject_xml(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'feed-inj-' . uniqid()]);
        $payload = 'Ürün]]><g:evil>hacked</g:evil><![CDATA[';
        $this->createProduct($payload);

        $body = $this->get("http://{$tenant->slug}.bizimsite.test/feed.xml?token={$tenant->fresh()->feed_secret}")
            ->streamedContent();

        // Üretilen feed hâlâ geçerli XML olmalı (parser CDATA-breakout nedeniyle patlamamalı).
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($body);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertNotFalse($doc, 'Feed geçersiz XML üretti: ' . json_encode(array_map(fn ($e) => $e->message, $errors)));

        // Enjekte edilen '<g:evil>' gerçek bir alt XML elemanı olarak açılmamış olmalı —
        // CDATA'nın içinde literal metin (title'ın text içeriği) olarak kalmalı.
        $namespaces = $doc->getNamespaces(true);
        $item = $doc->channel->item;
        $this->assertFalse(isset($item->children($namespaces['g'])->evil), 'Enjekte edilen <g:evil> gerçek bir XML elemanına dönüştü.');
        $this->assertStringContainsString($payload, (string) $item->children($namespaces['g'])->title);
    }

    public function test_brand_name_cannot_break_out_of_cdata(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'feed-inj-b-' . uniqid()]);
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'Marka]]><g:evil>x</g:evil><![CDATA[',
            'slug' => 'b-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createProduct('Normal Ürün', brandId: $brandId);

        $body = $this->get("http://{$tenant->slug}.bizimsite.test/feed.xml?token={$tenant->fresh()->feed_secret}")
            ->streamedContent();

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($body);
        libxml_clear_errors();

        $this->assertNotFalse($doc, 'Feed geçersiz XML üretti.');

        $namespaces = $doc->getNamespaces(true);
        $item = $doc->channel->item;
        $this->assertFalse(isset($item->children($namespaces['g'])->evil), 'Enjekte edilen <g:evil> gerçek bir XML elemanına dönüştü.');
    }
}
