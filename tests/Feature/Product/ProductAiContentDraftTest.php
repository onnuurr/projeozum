<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Atelier\Models\Material;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * AI ürün içeriği kayıt beklemeden çalışır: formdaki bilgilerle (yeni ürün dahil) açıklama ve SEO
 * üretilir; prompt bu bilgileri içerir, SEO çıktısı ProductSeoRules'tan geçer, hiçbir şey kaydedilmez.
 */
class ProductAiContentDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('creative.ai.gemini.api_key', 'test-key');
        config()->set('creative.ai.gemini.base_url', 'https://gen.example.test/v1beta');
        config()->set('creative.ai.gemini.text_model', 'gemini-2.5-flash');

        Permission::firstOrCreate(['name' => 'product.ai.generate', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('product.ai.generate');
    }

    private function fakeAi(array $json): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]], 200)]);
    }

    private function sentPrompt(): string
    {
        $prompt = '';
        Http::assertSent(function (Request $request) use (&$prompt) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return true;
        });

        return $prompt;
    }

    public function test_new_unsaved_product_gets_descriptions_and_seo_from_form_data(): void
    {
        $root = Category::create(['name' => 'Kadın', 'slug' => 'kadin', 'status' => 'active', 'sort_order' => 0]);
        $category = Category::create(['name' => 'Elbise', 'slug' => 'elbise', 'parent_id' => $root->id, 'status' => 'active', 'sort_order' => 0]);
        $brand = Brand::create(['name' => 'Ozum', 'slug' => 'ozum', 'sort_order' => 0]);
        $collar = ProductAttribute::create(['code' => 'yaka_tipi', 'name' => 'Yaka Tipi', 'type' => 'select', 'is_filterable' => true, 'sort_order' => 1]);
        $vYaka = $collar->options()->create(['name' => 'V Yaka', 'sort_order' => 1]);
        $category->productAttributes()->attach($collar->id, ['required' => false]);
        $fabric = Material::create(['code' => 'F-9', 'name' => 'Viskon Krep', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'specs' => ['composition' => '%100 viskon']]);

        $this->fakeAi([
            'public_name' => '"**Viskon Krep V Yaka Elbise**"',
            'public_description' => 'Akışkan viskon krep elbise.',
            'tenant_description' => 'Kompozisyon: %100 viskon.',
            'seo' => [
                'meta_title' => '**Viskon Krep V Yaka Elbise** | Ozum — yazlık, akışkan ve rahat kesim kadın elbise modeli',
                'meta_description' => 'Ozum viskon krep V yaka elbise.',
                'meta_keywords' => ['Viskon Elbise', 'viskon elbise', 'V Yaka Elbise', 'Ozum'],
            ],
        ]);

        $before = Product::count();

        $this->actingAs($this->admin)->postJson('/products/ai-content', [
            'parts' => ['descriptions', 'seo'],
            'name' => 'Krep Elbise',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'material' => '%100 Viskon',
            'attributes' => ['yaka_tipi' => $vYaka->id, 'olmayan_kod' => 'x'],
            'description_materials' => [['material_id' => $fabric->id, 'role' => 'primary_fabric']],
        ])
            ->assertOk()
            ->assertJsonPath('data.public_description', 'Akışkan viskon krep elbise.')
            ->assertJsonPath('data.public_name', 'Viskon Krep V Yaka Elbise')
            // Kural katmanı: markdown temizlenir, 60 karakterde kelime sınırında kesilir
            ->assertJsonPath('data.meta_title', 'Viskon Krep V Yaka Elbise | Ozum — yazlık, akışkan ve rahat')
            ->assertJsonPath('data.meta_keywords', 'viskon elbise, v yaka elbise, ozum')
            ->assertJsonPath('data.prompt_version', 'product-content.v4');

        $this->assertSame($before, Product::count(), 'taslak kaydedilmemeli');

        $prompt = $this->sentPrompt();
        foreach (['Krep Elbise', 'Ozum', 'Kadın > Elbise', 'Yaka Tipi: V Yaka', 'Kumaş içeriği: %100 Viskon', 'Viskon Krep', '"seo"', '"public_name"'] as $needle) {
            $this->assertStringContainsString($needle, $prompt);
        }
        $this->assertStringNotContainsString('olmayan_kod', $prompt);
    }

    public function test_seo_only_request_does_not_ask_for_descriptions(): void
    {
        $category = Category::create(['name' => 'Tişört', 'slug' => 'tisort', 'status' => 'active', 'sort_order' => 0]);
        $this->fakeAi(['seo' => ['meta_title' => 'Basic Tişört', 'meta_description' => 'Pamuklu basic tişört.', 'meta_keywords' => 'tişört']]);

        $this->actingAs($this->admin)->postJson('/products/ai-content', ['parts' => ['seo'], 'name' => 'Basic Tişört', 'category_id' => $category->id])
            ->assertOk()
            ->assertJsonPath('data.public_description', null)
            ->assertJsonPath('data.meta_description', 'Pamuklu basic tişört.');

        $prompt = $this->sentPrompt();
        $this->assertStringNotContainsString('public_description', $prompt);
        $this->assertStringNotContainsString('public_name', $prompt);
    }

    public function test_missing_seo_fields_is_an_error(): void
    {
        $category = Category::create(['name' => 'Tişört', 'slug' => 'tisort', 'status' => 'active', 'sort_order' => 0]);
        $this->fakeAi(['seo' => ['meta_keywords' => 'tişört']]);

        $this->actingAs($this->admin)->postJson('/products/ai-content', ['parts' => ['seo'], 'name' => 'X', 'category_id' => $category->id])
            ->assertStatus(502)
            ->assertJsonPath('message', __('product::messages.ai.missing_seo_fields'));
    }

    public function test_name_and_category_are_required(): void
    {
        $this->actingAs($this->admin)->postJson('/products/ai-content', ['parts' => ['descriptions']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id']);
    }

    public function test_missing_public_name_is_an_error(): void
    {
        $category = Category::create(['name' => 'Tişört', 'slug' => 'tisort', 'status' => 'active', 'sort_order' => 0]);
        $this->fakeAi(['public_description' => 'a', 'tenant_description' => 'b']);

        $this->actingAs($this->admin)->postJson('/products/ai-content', ['parts' => ['descriptions'], 'name' => 'X', 'category_id' => $category->id])
            ->assertStatus(502)
            ->assertJsonPath('message', __('product::messages.ai.missing_fields'));
    }
}
