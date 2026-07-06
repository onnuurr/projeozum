<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Atelier\Models\Material;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductDescriptionMaterial;
use Modules\Product\Services\Ai\ProductDescriptionPromptBuilder;
use Modules\Product\Services\Ai\PromptMaterialResolver;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GenerateProductDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('creative.ai.gemini.api_key', 'test-key');
        config()->set('creative.ai.gemini.base_url', 'https://gen.example.test/v1beta');
        config()->set('creative.ai.gemini.text_model', 'gemini-2.5-flash');

        Permission::firstOrCreate(['name' => 'product.ai.generate', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'product.view', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('product.ai.generate', 'product.view');

        $category = Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Basic Tişört',
            'sku' => 'BT-' . uniqid(), 'gender' => 'Unisex', 'price' => 199.90,
        ]);

        $fabric = Material::create([
            'code' => 'F-01', 'name' => 'Pamuklu Süprem', 'type' => 'kumas',
            'unit' => 'metre', 'unit_cost' => 30,
            'specs' => ['composition' => '%100 pamuk', 'gsm' => 180, 'weave' => 'jarse'],
        ]);
        ProductDescriptionMaterial::create([
            'product_id' => $this->product->id, 'material_id' => $fabric->id,
            'role' => 'primary_fabric', 'sort_order' => 0,
        ]);
    }

    public function test_generate_returns_two_tones_and_stamps_product(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_description' => '**Yaz için hafif** ve nefes alan bir tişört.',
                    'tenant_description' => 'Kompozisyon: %100 pamuk. Gramaj: 180 gsm. Jarse örgü.',
                ])]]],
            ]]], 200),
            '*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_description' => '**Yaz için hafif** ve nefes alan bir tişört.',
                    'tenant_description' => 'Kompozisyon: %100 pamuk. Gramaj: 180 gsm. Jarse örgü.',
                ])]]],
            ]]], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/products/{$this->product->id}/ai-description")
            ->assertOk()
            ->assertJsonPath('data.public_description', '**Yaz için hafif** ve nefes alan bir tişört.')
            ->assertJsonPath('data.tenant_description', 'Kompozisyon: %100 pamuk. Gramaj: 180 gsm. Jarse örgü.');

        $this->assertNotNull($this->product->fresh()->ai_generated_at);
    }

    public function test_generate_handles_ai_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response('{"candidates":[]}', 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/products/{$this->product->id}/ai-description")
            ->assertStatus(502);
    }

    public function test_generate_requires_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('product.view');

        $this->actingAs($viewer)
            ->postJson("/products/{$this->product->id}/ai-description")
            ->assertForbidden();
    }

    public function test_prompt_builder_embeds_materials_and_tenant_line(): void
    {
        /** @var PromptMaterialResolver $resolver */
        $resolver = app(PromptMaterialResolver::class);
        $lines = $resolver->forPrompt($this->product);

        $builder = app(ProductDescriptionPromptBuilder::class);
        $prompt  = $builder->build($this->product, $lines, null);

        $this->assertStringContainsString('Basic Tişört', $prompt);
        $this->assertStringContainsString('Ana kumaş', $prompt);
        $this->assertStringContainsString('%100 pamuk', $prompt);
        $this->assertStringContainsString('180 gsm', $prompt);
        $this->assertStringContainsString('geniş bayi ağımıza', $prompt);
    }
}
