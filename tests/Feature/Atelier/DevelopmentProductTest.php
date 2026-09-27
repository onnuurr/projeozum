<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Exceptions\InvalidProductTransitionException;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\ProductService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Üretilecek Ürün": reçete + ön maliyetle geçici kart (status=development) açılır,
 * mağazaya/bayiye sızmaz, ilk iş emri tamamlanınca ürüne (draft) döner.
 */
class DevelopmentProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Material $kumas;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['product.add', 'atelier.bom.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['product.add', 'atelier.bom.manage']);

        $this->category = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $this->kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 100, 'current_stock' => 1000]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Keten Gömlek',
            'sku'         => 'KTN-01',
            'category_id' => $this->category->id,
            'gender'      => 'Kadın',
            'variants'    => [
                ['size' => 'M', 'color_name' => 'Ekru', 'sku' => 'KTN-01-M-EKRU'],
            ],
            'lines' => [
                ['material_id' => $this->kumas->id, 'quantity_per_unit' => 1.5, 'waste_pct' => 10],
            ],
            'extra_cost_per_unit' => 35,
            'margin_pct'          => 50,
            'price'               => 300,
        ], $overrides);
    }

    private function jobProperty(object $job, string $name): mixed
    {
        return (new \ReflectionProperty($job, $name))->getValue($job);
    }

    public function test_store_opens_development_card_with_bom_and_estimated_cost(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/atelier/new-product', $this->payload())
            ->assertRedirect();

        $product = Product::where('sku', 'KTN-01')->firstOrFail();

        $this->assertSame(Product::STATUS_DEVELOPMENT, $product->status);
        $this->assertSame(Product::SOURCING_MANUFACTURED, $product->sourcing);
        // 1.5m × 1.10 fire × 100₺ = 165 + 35 işçilik = 200
        $this->assertEquals(200, (float) $product->purchase_price);
        $this->assertEquals(300, (float) $product->price);
        $this->assertSame(0, (int) $product->variants()->first()->stock);

        $bom = ProductBom::where('product_id', $product->id)->where('is_active', true)->firstOrFail();
        $this->assertEquals(35, (float) $bom->extra_cost_per_unit);
        $this->assertSame(1, $bom->lines()->count());

        // Geçici kart mağazaya gönderilmez.
        Queue::assertNotPushed(PushProductToBagisto::class);
    }

    public function test_store_requires_both_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('product.add');

        $this->actingAs($user)->post('/atelier/new-product', $this->payload())->assertForbidden();
        $this->actingAs($user)->get('/atelier/new-product')->assertForbidden();
        $this->assertDatabaseMissing('products', ['sku' => 'KTN-01']);
    }

    public function test_completing_first_production_order_promotes_card_to_draft(): void
    {
        $this->actingAs($this->admin)->post('/atelier/new-product', $this->payload());
        $product = Product::where('sku', 'KTN-01')->firstOrFail();
        $variant = $product->variants()->first();
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'status' => ProductionOrder::STATUS_DRAFT, 'planned_qty' => 10,
        ]);
        $order->items()->create(['product_variant_id' => $variant->id, 'planned_qty' => 10]);

        $service = app(ProductionOrderService::class);
        $service->plan($order);
        $order->items()->first()->update(['produced_qty' => 10]);

        Queue::fake();
        $service->complete($order->fresh());

        $product->refresh();
        $this->assertSame(Product::STATUS_DRAFT, $product->status);
        // Gerçek maliyet: 10 adet × 1.65m × 100₺ = 1650 / 10 = 165 (işçilik adımı yok)
        $this->assertEquals(165, (float) $product->purchase_price);
        $this->assertDatabaseHas('stocks', ['product_variant_id' => $variant->id, 'quantity' => 10]);
        $this->assertDatabaseHas('product_status_histories', [
            'product_id' => $product->id, 'from_status' => 'development', 'to_status' => 'draft',
        ]);

        // Ürün mağazada ilk kez oluşturulur.
        Queue::assertPushed(PushProductToBagisto::class, fn ($job) => $this->jobProperty($job, 'productId') === $product->id
            && $this->jobProperty($job, 'event') === 'created');
    }

    public function test_development_card_cannot_be_published_manually(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DEVELOPMENT]);

        $this->expectException(InvalidProductTransitionException::class);

        app(ProductService::class)->transitionStatus($product, Product::STATUS_PUBLISHED, $this->admin);
    }

    public function test_development_card_is_hidden_from_tenant_catalog_scope(): void
    {
        $dev = Product::factory()->create(['status' => Product::STATUS_DEVELOPMENT]);
        $live = Product::factory()->create(['status' => Product::STATUS_PUBLISHED]);

        $ids = Product::query()->accessibleToTenant(999)->pluck('id')->all();

        $this->assertContains($live->id, $ids);
        $this->assertNotContains($dev->id, $ids);
        // Yönetici (tenant yok) geçici kartı görür.
        $this->assertContains($dev->id, Product::query()->accessibleToTenant(null)->pluck('id')->all());
    }
}
