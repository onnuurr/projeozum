<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\StockOperation;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Toplu stok işlemi (StockService::bulk / StockBulkController): giriş, çıkış, sayım; tek transaction;
 * her değişiklik işleme bağlı bir hareket (log).
 */
class StockBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    /** @var list<ProductVariant> */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();   // StockChanged → Bagisto push
        Permission::firstOrCreate(['name' => 'stock.manage', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('stock.manage');
        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);

        $category = Category::create(['name' => 'Pijama', 'slug' => 'p-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Pijama Takımı', 'sku' => 'PJ-1', 'price' => 100]);
        foreach (['3 Yaş', '4 Yaş', '5 Yaş'] as $i => $size) {
            $this->variants[] = $product->variants()->create(['sku' => "PJ-1-{$i}", 'size' => $size, 'price' => 100, 'stock' => 0, 'sort_order' => $i]);
        }
        Stock::create(['product_variant_id' => $this->variants[0]->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5]);
        $this->variants[0]->update(['stock' => 5]);
    }

    private function submit(string $mode, array $quantities, ?string $note = null)
    {
        return $this->actingAs($this->user)->post('/products/stocks/bulk', [
            'warehouse_id' => $this->warehouse->id, 'mode' => $mode, 'note' => $note,
            'lines' => collect($quantities)->map(fn ($q, $i) => ['variant_id' => $this->variants[$i]->id, 'quantity' => $q])->values()->all(),
        ]);
    }

    public function test_in_adds_to_every_variant_and_logs_one_operation(): void
    {
        $this->submit('in', [2, 3, 4], 'Tedarikçi teslimatı')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.stocks.history', ['operation_id' => StockOperation::sole()->id]));

        $this->assertSame([7, 3, 4], array_map(fn ($v) => (int) $v->fresh()->stock, $this->variants));
        $operation = StockOperation::sole();
        $this->assertSame(['in', 3, 9, 0, $this->user->id, 'Tedarikçi teslimatı'],
            [$operation->mode, $operation->line_count, $operation->total_in, $operation->total_out, $operation->user_id, $operation->note]);
        $this->assertSame(3, $operation->movements()->count());
        $this->assertTrue($operation->movements->every(fn ($m) => $m->type === StockMovement::TYPE_IN && $m->user_id === $this->user->id));
    }

    public function test_count_sets_quantities_and_skips_unchanged(): void
    {
        $this->submit('count', [5, 10, 0])->assertSessionHasNoErrors();

        $this->assertSame([5, 10, 0], array_map(fn ($v) => (int) $v->fresh()->stock, $this->variants));
        $operation = StockOperation::sole();
        $this->assertSame([1, 2, 10], [$operation->line_count, $operation->skipped_count, $operation->total_in]);
        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $operation->movements()->sole()->type);
    }

    public function test_out_that_would_go_negative_writes_nothing(): void
    {
        $this->submit('out', [2, 1])->assertSessionHasErrors('lines.1.quantity');

        $this->assertSame(0, StockOperation::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(5, (int) $this->variants[0]->fresh()->stock);
    }

    public function test_products_endpoint_returns_all_variants_with_warehouse_quantities(): void
    {
        $rows = $this->actingAs($this->user)
            ->getJson('/products/stocks/bulk/products?warehouse_id=' . $this->warehouse->id . '&q=pj-1-2')
            ->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame([5, 0, 0], array_column($rows[0]['variants'], 'quantity'));
    }

    public function test_history_filters_by_operation(): void
    {
        $this->submit('in', [1, 1, 1]);
        $this->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->actingAs($this->user)->get('/products/stocks/history?operation_id=' . StockOperation::sole()->id)
            ->assertInertia(fn ($page) => $page->component('Product::StockHistory', false)
                ->where('operation.lineCount', 3)->has('movements.data', 3)->has('operations', 1));
    }

    public function test_requires_stock_manage(): void
    {
        $this->actingAs(User::factory()->create())->post('/products/stocks/bulk', [])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/products/stocks/bulk')->assertForbidden();
    }
}
