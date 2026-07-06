<?php

namespace Tests\Feature\Tenant\Feed;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAccessRule;
use Tests\TestCase;

class TenantFeedTokenTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name, ?int $brandId = null): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'name' => $name, 'slug' => str_replace(' ', '-', strtolower($name)) . '-' . uniqid(),
            'sku' => 'SKU-' . uniqid(), 'price' => 100,
            'brand_id' => $brandId, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_wrong_token_returns_404(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'feed-' . uniqid()]);

        $this->get("http://{$tenant->slug}.bizimsite.test/feed.xml?token=wrong")
            ->assertStatus(404);
    }

    public function test_correct_token_returns_xml(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'feed-' . uniqid()]);
        $this->createProduct('Ürün A');
        $this->createProduct('Ürün B');

        $response = $this->get("http://{$tenant->slug}.bizimsite.test/feed.xml?token={$tenant->fresh()->feed_secret}");

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('<rss', $body);
        $this->assertStringContainsString('Ürün A', $body);
        $this->assertStringContainsString('Ürün B', $body);
    }

    public function test_blocked_products_excluded(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'feed-' . uniqid()]);
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'BloklananMarka', 'slug' => 'b-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createProduct('Bloklu Ürün', brandId: $brandId);
        $this->createProduct('Açık Ürün');

        TenantAccessRule::create([
            'tenant_id' => $tenant->id,
            'scope_type' => TenantAccessRule::SCOPE_BRAND,
            'scope_id' => $brandId,
            'is_blocked' => true,
        ]);

        $body = $this->get("http://{$tenant->slug}.bizimsite.test/feed.xml?token={$tenant->fresh()->feed_secret}")
            ->streamedContent();

        $this->assertStringNotContainsString('Bloklu Ürün', $body);
        $this->assertStringContainsString('Açık Ürün', $body);
    }
}
