<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;
use Modules\Atelier\Services\MaterialReservationService;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Product\Events\StockChanged;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Tests\TestCase;

class ProductionOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductionOrderService::class);
    }

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'price' => 10]);
        $v1 = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $v2 = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '4', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 1]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);

        $kesim = Operation::create(['code' => 'kesim', 'name' => 'Kesim', 'default_location' => 'in_house', 'consumes_materials' => true]);
        $dikim = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);
        $paket = Operation::create(['code' => 'paket', 'name' => 'Paket', 'default_location' => 'in_house']);
        $fason = FasonSupplier::create(['name' => 'Fasoncu A']);

        return compact('product', 'v1', 'v2', 'warehouse', 'kumas', 'kesim', 'dikim', 'paket', 'fason');
    }

    private function createOrder(array $s, int $qty = 100): ProductionOrder
    {
        $half = intdiv($qty, 2);

        return $this->service->create([
            'product_id'   => $s['product']->id,
            'warehouse_id' => $s['warehouse']->id,
            'planned_qty'  => $qty,
            'items'        => [
                ['product_variant_id' => $s['v1']->id, 'planned_qty' => $half],
                ['product_variant_id' => $s['v2']->id, 'planned_qty' => $qty - $half],
            ],
            'steps'        => [
                ['operation_id' => $s['kesim']->id, 'sequence' => 1, 'location_type' => 'in_house', 'unit_cost' => 1],
                ['operation_id' => $s['dikim']->id, 'sequence' => 2, 'location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id, 'unit_cost' => 5],
                ['operation_id' => $s['paket']->id, 'sequence' => 3, 'location_type' => 'in_house', 'unit_cost' => 0.5],
            ],
        ], null);
    }

    /** @return array<int, ProductionOrderStep> */
    private function steps(ProductionOrder $order): array
    {
        return $order->steps()->get()->all();
    }

    public function test_create_rejects_item_total_mismatch(): void
    {
        $s = $this->scenario();
        $this->expectException(InvalidArgumentException::class);

        $this->service->create([
            'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id, 'planned_qty' => 100,
            'items' => [['product_variant_id' => $s['v1']->id, 'planned_qty' => 60]],
            'steps' => [['operation_id' => $s['kesim']->id, 'sequence' => 1, 'location_type' => 'in_house']],
        ], null);
    }

    public function test_create_requires_fason_supplier_for_fason_step_and_logs_decisions(): void
    {
        $s = $this->scenario();

        try {
            $this->service->create([
                'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id, 'planned_qty' => 10,
                'items' => [['product_variant_id' => $s['v1']->id, 'planned_qty' => 10]],
                'steps' => [['operation_id' => $s['dikim']->id, 'sequence' => 1, 'location_type' => 'fason']],
            ], null);
            $this->fail('Fasoncu zorunlu istisnası beklenmişti.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('fasoncu', $e->getMessage());
        }

        $order = $this->createOrder($s);
        $this->assertDatabaseCount('production_step_decisions', 3);
        $this->assertDatabaseHas('production_step_decisions', ['location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id]);
        $this->assertCount(3, $this->steps($order));
    }

    public function test_plan_reserves_without_consuming(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 100);

        $this->service->plan($order);

        $this->assertSame('1000.000', $s['kumas']->fresh()->current_stock);
        $this->assertDatabaseMissing('material_movements', ['production_order_id' => $order->id]);
        $this->assertEquals(900, app(MaterialReservationService::class)->available($s['kumas']->fresh()));
        $this->assertSame('1000.00', $order->fresh()->material_cost);
        $this->assertSame(ProductionOrder::STATUS_PLANNED, $order->fresh()->status);
    }

    public function test_second_order_cannot_over_reserve(): void
    {
        $s = $this->scenario();
        $s['kumas']->update(['current_stock' => 150]);
        $this->service->plan($this->createOrder($s, 100));

        $second = $this->createOrder($s, 100); // kullanılabilir 50m, gerekli 100m
        try {
            $this->service->plan($second);
            $this->fail('Yetersiz hammadde istisnası beklenmişti.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Yetersiz hammadde', $e->getMessage());
        }
        $this->assertSame(ProductionOrder::STATUS_DRAFT, $second->fresh()->status);
    }

    public function test_batches_are_sequential_and_consume_at_cutting(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 100);
        $this->service->plan($order);
        [$kesim, $dikim] = $this->steps($order);

        // Kesim yapılmadan dikime iş girilemez.
        try {
            $this->service->recordBatch($dikim, 1, 0, null, null);
            $this->fail('Sıra istisnası beklenmişti.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('işlenmemiş adet 0', $e->getMessage());
        }

        $this->service->recordBatch($kesim, 58, 2, null, null); // 60 adet kesildi → 60m düşer
        $this->assertSame('940.000', $s['kumas']->fresh()->current_stock);
        $this->assertSame(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        // Dikime en fazla kesimin sağlam çıkışı (58) girer; kısmi ilerleme serbest.
        $this->service->recordBatch($dikim->fresh(), 30, 0, null, null);
        $this->expectException(InvalidArgumentException::class);
        $this->service->recordBatch($dikim->fresh(), 29, 0, null, null);
    }

    public function test_step_statuses_are_derived(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 10);
        $this->service->plan($order);
        [$kesim, $dikim] = $this->steps($order);

        $this->service->recordBatch($kesim, 10, 0, null, null);
        $this->service->recordBatch($dikim->fresh(), 4, 0, null, null);

        $this->assertSame(ProductionOrderStep::STATUS_DONE, $kesim->fresh()->status);
        $this->assertSame(ProductionOrderStep::STATUS_IN_PROGRESS, $dikim->fresh()->status);

        $this->service->recordBatch($dikim->fresh(), 5, 1, null, null);
        $this->assertSame(ProductionOrderStep::STATUS_DONE, $dikim->fresh()->status);
        $this->assertSame(9, $dikim->fresh()->output_qty);
        $this->assertSame('45.00', $dikim->fresh()->step_cost); // 9 × 5₺
    }

    public function test_decision_change_is_logged_and_locked_after_batch(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 10);
        [, $dikim] = $this->steps($order);

        $this->service->changeDecision($dikim, ['location_type' => 'in_house', 'unit_cost' => 3], null);
        $this->assertSame('in_house', $dikim->fresh()->location_type);
        $this->assertNull($dikim->fresh()->fason_supplier_id);
        $this->assertSame(2, $dikim->decisions()->count());

        $this->service->plan($order);
        [$kesim] = $this->steps($order);
        $this->service->recordBatch($kesim, 10, 0, null, null);
        $this->service->recordBatch($dikim->fresh(), 1, 0, null, null);

        $this->expectException(InvalidArgumentException::class);
        $this->service->changeDecision($dikim->fresh(), ['location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id], null);
    }

    public function test_partial_receive_goes_through_stock_service(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 10);
        $this->service->plan($order);
        [$kesim, $dikim, $paket] = $this->steps($order);
        $this->service->recordBatch($kesim, 10, 0, null, null);
        $this->service->recordBatch($dikim->fresh(), 10, 0, null, null);
        $this->service->recordBatch($paket->fresh(), 6, 0, null, null);

        Event::fake([StockChanged::class]);

        try {
            $this->service->receive($order, [$s['v1']->id => 7], null);
            $this->fail('Alınabilir adet istisnası beklenmişti.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Stoğa alınabilecek adet 6', $e->getMessage());
        }

        $this->service->receive($order, [$s['v1']->id => 4, $s['v2']->id => 2], null);

        Event::assertDispatched(StockChanged::class, 2);
        $this->assertDatabaseHas('stocks', ['product_variant_id' => $s['v1']->id, 'warehouse_id' => $s['warehouse']->id, 'quantity' => 4]);
        $this->assertSame(4, (int) $s['v1']->fresh()->stock);
        $this->assertSame(6, $order->fresh()->received_qty);
        $this->assertSame(0, $this->service->receivable($order->fresh()));
    }

    public function test_close_rolls_up_actual_cost_and_releases_reservation(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 10);
        $this->service->plan($order);
        [$kesim, $dikim, $paket] = $this->steps($order);
        $this->service->recordBatch($kesim, 8, 0, null, null);     // 8m tüketim, 2 adetlik rezerv kalır
        $this->service->recordBatch($dikim->fresh(), 8, 0, null, null);
        $this->service->recordBatch($paket->fresh(), 8, 0, null, null);
        $this->service->receive($order, [$s['v1']->id => 5, $s['v2']->id => 3], null);

        $this->service->close($order->fresh(), null);
        $order->refresh();

        // malzeme 8m × 10 = 80; işçilik kesim 8×1 + paket 8×0.5 = 12; fason 8×5 = 40
        $this->assertSame('80.00', $order->material_cost);
        $this->assertSame('12.00', $order->labor_cost);
        $this->assertSame('40.00', $order->fason_cost);
        $this->assertSame('132.00', $order->total_cost);
        $this->assertSame('16.50', $order->unit_cost);
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->status);
        $this->assertEquals(16.5, (float) $s['product']->fresh()->purchase_price);
        $this->assertEquals(992, app(MaterialReservationService::class)->available($s['kumas']->fresh()));
    }

    public function test_close_requires_received_stock_and_no_pending_output(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 10);
        $this->service->plan($order);

        $this->expectExceptionMessage('iptal edin');
        $this->service->close($order->fresh(), null);
    }

    public function test_cancel_releases_reservation_but_keeps_consumed(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 100);
        $this->service->plan($order);
        [$kesim] = $this->steps($order);
        $this->service->recordBatch($kesim, 40, 0, null, null);

        $this->service->cancel($order->fresh(), User::factory()->create());

        $this->assertSame(ProductionOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame('960.000', $s['kumas']->fresh()->current_stock);
        $this->assertEquals(960, app(MaterialReservationService::class)->available($s['kumas']->fresh()));
    }

    public function test_cancel_rejected_after_stock_receipt(): void
    {
        $s = $this->scenario();
        $order = $this->createOrder($s, 2);
        $this->service->plan($order);
        foreach ($this->steps($order) as $step) {
            $this->service->recordBatch($step->fresh(), 2, 0, null, null);
        }
        $this->service->receive($order, [$s['v1']->id => 2], null);

        $this->expectExceptionMessage('kapatın');
        $this->service->cancel($order->fresh(), null);
    }
}
