<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Exceptions\InvalidOrderTransitionException;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\OrderService;
use Modules\Product\Services\StockService;
use Modules\Tenant\Models\TenantCreditLedger;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class OrderCancelSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // StockChanged → PushStockToBagisto, QUEUE_CONNECTION=sync (phpunit.xml)
        // altında fake edilmeden gerçek bir HTTP isteğine çıkıyordu.
        Queue::fake();
    }

    public function test_cancel_mirrors_split_allocations_and_refunds_credit(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);
        $order  = Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_PENDING, 'total' => 800]);

        $product = Product::factory()->create(['price' => 100]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);

        $whDefault = Warehouse::factory()->default()->create();
        $whOther   = Warehouse::factory()->create();

        $stockDefault = Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $whDefault->id, 'quantity' => 5,
        ]);
        $stockOther = Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $whOther->id, 'quantity' => 10,
        ]);

        OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'product_name'       => $product->name,
            'qty'                => 8,
            'unit_price'         => 100,
            'total_price'        => 800,
        ]);

        // Sipariş kesimi: bölünmüş depo tahsisi (varsayılan 5 + diğer 3).
        app(StockService::class)->decrementForOrder($order, $order->items()->get());
        $this->assertSame(0, $stockDefault->fresh()->quantity);
        $this->assertSame(7, $stockOther->fresh()->quantity);

        // Kredi çekimini simüle et: iptal iadesinin ledger'da görünür olması için.
        app(\Modules\Tenant\Services\TenantCreditService::class)
            ->charge($tenant, 800, 'order', orderId: $order->id);
        $this->assertEqualsWithDelta(800.0, (float) $tenant->fresh()->current_balance, 0.01);

        // İptal geçişi.
        app(OrderService::class)->transition($order, Order::STATUS_CANCELLED, User::factory()->create(), 'Müşteri iptali');

        // Aynalı iade: her OUT için bir IN, stoklar tam geri geldi.
        $this->assertSame(5, $stockDefault->fresh()->quantity);
        $this->assertSame(10, $stockOther->fresh()->quantity);

        $ins = StockMovement::where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->where('type', StockMovement::TYPE_IN)
            ->get();
        $this->assertCount(2, $ins);

        // Kredi iadesi ledger'da: order_cancelled credit satırı, bakiye sıfırlandı.
        $refund = TenantCreditLedger::where('tenant_id', $tenant->id)
            ->where('type', TenantCreditLedger::TYPE_CREDIT)
            ->where('reason', 'order_cancelled')
            ->first();
        $this->assertNotNull($refund);
        $this->assertEqualsWithDelta(800.0, (float) $refund->amount, 0.01);
        // Faz 4: iade satırı artık order_id taşır → admin sipariş detayı "Kredi Hareketleri"nde görünür.
        $this->assertSame($order->id, $refund->order_id);
        $this->assertEqualsWithDelta(0.0, (float) $tenant->fresh()->current_balance, 0.01);
    }

    public function test_double_cancel_is_blocked_by_transition_map(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);
        $order  = Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_PENDING, 'total' => 100]);

        app(OrderService::class)->transition($order, Order::STATUS_CANCELLED, User::factory()->create(), null);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderService::class)->transition($order->fresh(), Order::STATUS_CANCELLED, User::factory()->create(), null);
    }
}
