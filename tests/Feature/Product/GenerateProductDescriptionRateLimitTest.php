<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * /products/{product}/ai-description her çağrıda ücretli bir Gemini isteği tetikliyordu
 * ve hiç hız sınırı yoktu — script/ele geçirilmiş hesap sınırsız fatura şişirebilirdi.
 * routes/Product/web.php'ye throttle:15,1 eklendi; bu test korumayı doğrular.
 */
class GenerateProductDescriptionRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_description_is_rate_limited_after_15_requests_per_minute(): void
    {
        config()->set('creative.ai.gemini.api_key', 'test-key');
        config()->set('creative.ai.gemini.base_url', 'https://gen.example.test/v1beta');
        config()->set('creative.ai.gemini.text_model', 'gemini-2.5-flash');

        Permission::firstOrCreate(['name' => 'product.ai.generate', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('product.ai.generate');

        $category = Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Basic Tişört',
            'sku' => 'BT-' . uniqid(), 'gender' => 'Unisex', 'price' => 199.90,
        ]);

        Http::fake([
            '*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_description' => 'x',
                    'tenant_description' => 'y',
                ])]]],
            ]]], 200),
        ]);

        $this->actingAs($admin);
        for ($i = 0; $i < 15; $i++) {
            $this->postJson("/products/{$product->id}/ai-description")->assertOk();
        }

        $this->postJson("/products/{$product->id}/ai-description")->assertStatus(429);
    }
}
