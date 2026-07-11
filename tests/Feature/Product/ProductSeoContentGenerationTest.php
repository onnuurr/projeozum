<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Atelier\Models\ProductBom;
use Modules\Product\Jobs\GenerateProductSeoContentJob;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\ProductDescriptionResult;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * Reçete (BOM) kaydedilince AI ile SEO uyumlu başlık + açıklama + meta üretilip
 * ürüne kaydedilmesini doğrular (#8). Üretici sahtelenir; ağ çağrısı yapılmaz.
 */
class ProductSeoContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Ham Ürün',
            'sku' => 'HM-' . uniqid(), 'gender' => 'Unisex', 'price' => 100,
        ]);
    }

    private function fakeGenerator(): void
    {
        $this->app->instance(ProductDescriptionGenerator::class, new class implements ProductDescriptionGenerator {
            public function generate(Product $product, ?Tenant $tenant = null): ProductDescriptionResult
            {
                return new ProductDescriptionResult(
                    publicDescription: 'Storefront için etkileyici açıklama.',
                    tenantDescription: 'Bayi için teknik açıklama.',
                    model: 'test-model',
                    publicName: 'SEO Uyumlu Başlık',
                    metaTitle: 'Meta Başlık',
                    metaDescription: 'Meta açıklama cümlesi.',
                    metaKeywords: 'tişört, pamuk, yazlık',
                );
            }
        });
    }

    public function test_job_persists_ai_seo_content_to_product(): void
    {
        $this->fakeGenerator();
        $product = $this->product();

        (new GenerateProductSeoContentJob($product->id))
            ->handle($this->app->make(ProductDescriptionGenerator::class));

        $fresh = $product->fresh();
        $this->assertSame('SEO Uyumlu Başlık', $fresh->public_name);
        $this->assertSame('Storefront için etkileyici açıklama.', $fresh->public_description);
        $this->assertSame('Bayi için teknik açıklama.', $fresh->tenant_description);
        $this->assertSame('Meta Başlık', $fresh->meta_title);
        $this->assertSame('Meta açıklama cümlesi.', $fresh->meta_description);
        $this->assertSame('tişört, pamuk, yazlık', $fresh->meta_keywords);
        $this->assertNotNull($fresh->ai_generated_at);
    }

    public function test_creating_bom_dispatches_seo_job_when_enabled(): void
    {
        Queue::fake();
        config()->set('product.ai.auto_seo_on_bom', true);
        $product = $this->product();

        ProductBom::create(['product_id' => $product->id, 'name' => 'Reçete', 'is_active' => true]);

        Queue::assertPushed(
            GenerateProductSeoContentJob::class,
            fn (GenerateProductSeoContentJob $job) => $job->productId === $product->id,
        );
    }

    public function test_creating_bom_does_not_dispatch_when_disabled(): void
    {
        Queue::fake();
        config()->set('product.ai.auto_seo_on_bom', false);
        $product = $this->product();

        ProductBom::create(['product_id' => $product->id, 'name' => 'Reçete', 'is_active' => true]);

        Queue::assertNotPushed(GenerateProductSeoContentJob::class);
    }

    public function test_generator_parses_seo_fields_from_ai_response(): void
    {
        config()->set('creative.ai.gemini.api_key', 'test-key');
        config()->set('creative.ai.gemini.base_url', 'https://gen.example.test/v1beta');
        config()->set('creative.ai.gemini.text_model', 'gemini-2.5-flash');

        Http::fake([
            '*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_name'        => 'Pamuklu Basic Tişört',
                    'public_description' => 'Yumuşak ve nefes alan.',
                    'tenant_description' => '%100 pamuk, 180 gsm.',
                    'meta_title'         => 'Pamuklu Tişört | Marka',
                    'meta_description'   => 'Nefes alan pamuklu tişört.',
                    'meta_keywords'      => 'tişört, pamuk',
                ])]]],
            ]]], 200),
        ]);

        $generator = $this->app->make(ProductDescriptionGenerator::class);
        $result = $generator->generate($this->product());

        $this->assertSame('Pamuklu Basic Tişört', $result->publicName);
        $this->assertSame('Pamuklu Tişört | Marka', $result->metaTitle);
        $this->assertSame('Nefes alan pamuklu tişört.', $result->metaDescription);
        $this->assertSame('tişört, pamuk', $result->metaKeywords);
    }
}
