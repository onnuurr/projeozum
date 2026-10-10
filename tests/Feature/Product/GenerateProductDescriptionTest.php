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
            'sku' => 'BT-' . uniqid(), 'price' => 199.90,
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

    /** Formdaki bilgiler (düzenleme: product_id ile). */
    private function payload(array $override = []): array
    {
        return [
            'parts' => ['descriptions'],
            'product_id' => $this->product->id,
            'name' => $this->product->name,
            'category_id' => $this->product->category_id,
            ...$override,
        ];
    }

    public function test_generate_returns_two_tones_and_stamps_product(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_name' => 'Basic Pamuklu Tişört',
                    'public_description' => '**Yaz için hafif** ve nefes alan bir tişört.',
                    'tenant_description' => 'Kompozisyon: %100 pamuk. Gramaj: 180 gsm. Jarse örgü.',
                ])]]],
            ]]], 200),
            '*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'public_name' => 'Basic Pamuklu Tişört',
                    'public_description' => '**Yaz için hafif** ve nefes alan bir tişört.',
                    'tenant_description' => 'Kompozisyon: %100 pamuk. Gramaj: 180 gsm. Jarse örgü.',
                ])]]],
            ]]], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/products/ai-content', $this->payload())
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
            ->postJson('/products/ai-content', $this->payload())
            ->assertStatus(502);
    }

    /**
     * Sağlayıcı hataları kullanıcıya dil dosyasından (creative::messages.ai_provider.*) gösterilir;
     * Google'ın ham İngilizce mesajı/gövdesi cevaba sızmaz.
     */
    public function test_provider_errors_are_shown_from_lang_files_without_raw_body(): void
    {
        $cases = [
            'denied' => [403, ['error' => ['code' => 403, 'message' => 'Your project has been denied access. Please contact support.', 'status' => 'PERMISSION_DENIED']]],
            'quota' => [429, ['error' => ['code' => 429, 'message' => 'Resource has been exhausted', 'status' => 'RESOURCE_EXHAUSTED']]],
            'auth' => [400, ['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT']]],
            'unavailable' => [503, ['error' => ['code' => 503, 'message' => 'The model is overloaded.', 'status' => 'UNAVAILABLE']]],
            'blocked' => [200, ['promptFeedback' => ['blockReason' => 'SAFETY'], 'candidates' => []]],
        ];

        $sequence = Http::fakeSequence();
        foreach ($cases as [$status, $body]) {
            $sequence->push($body, $status);
        }

        foreach ($cases as $reason => [$status, $body]) {

            $message = $this->actingAs($this->admin)
                ->postJson('/products/ai-content', $this->payload())
                ->assertStatus(502)
                ->json('message');

            $this->assertSame(
                __('product::messages.ai.call_failed', ['error' => __("creative::messages.ai_provider.{$reason}")]),
                $message,
                $reason,
            );
            $this->assertStringNotContainsString((string) ($body['error']['message'] ?? 'SAFETY'), $message);
        }

        config()->set('creative.ai.gemini.api_key', '');
        $this->actingAs($this->admin)->postJson('/products/ai-content', $this->payload())
            ->assertStatus(502)
            ->assertJsonPath('message', __('product::messages.ai.call_failed', ['error' => __('creative::messages.ai_provider.missing_key')]));
    }

    public function test_generate_requires_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('product.view');

        $this->actingAs($viewer)
            ->postJson('/products/ai-content', $this->payload())
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
