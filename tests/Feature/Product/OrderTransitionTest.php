<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Exceptions\InvalidOrderTransitionException;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderStatusHistory;
use Modules\Product\Services\OrderService;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class OrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = Order::STATUS_PENDING): Order
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);

        return Order::factory()->forTenant($tenant)->create(['status' => $status, 'total' => 500]);
    }

    private function actor(): User
    {
        return User::factory()->create();
    }

    public function test_legal_transition_updates_status_and_writes_history(): void
    {
        $order = $this->order(Order::STATUS_PENDING);
        $actor = $this->actor();

        $result = app(OrderService::class)->transition($order, Order::STATUS_CONFIRMED, $actor, 'Onaylandı');

        $this->assertSame(Order::STATUS_CONFIRMED, $result->status);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(Order::STATUS_PENDING, $history->from_status);
        $this->assertSame(Order::STATUS_CONFIRMED, $history->to_status);
        $this->assertSame($actor->id, $history->user_id);
        $this->assertSame('Onaylandı', $history->note);
    }

    public function test_full_happy_path_matrix_writes_one_history_row_per_step(): void
    {
        $order = $this->order(Order::STATUS_PENDING);
        $actor = $this->actor();
        $service = app(OrderService::class);

        $service->transition($order, Order::STATUS_CONFIRMED, $actor, null);
        $service->transition($order, Order::STATUS_PREPARING, $actor, null);
        $service->transition($order, Order::STATUS_SHIPPED, $actor, null);
        $service->transition($order, Order::STATUS_DELIVERED, $actor, null);

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertSame(4, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_illegal_transition_throws_and_does_not_change_status(): void
    {
        $order = $this->order(Order::STATUS_PENDING);

        try {
            app(OrderService::class)->transition($order, Order::STATUS_SHIPPED, $this->actor(), null);
            $this->fail('InvalidOrderTransitionException bekleniyordu.');
        } catch (InvalidOrderTransitionException $e) {
            // beklenen
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_shipped_cannot_be_cancelled(): void
    {
        $order = $this->order(Order::STATUS_SHIPPED);

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderService::class)->transition($order, Order::STATUS_CANCELLED, $this->actor(), null);
    }

    public function test_delivered_is_terminal(): void
    {
        $order = $this->order(Order::STATUS_DELIVERED);

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderService::class)->transition($order, Order::STATUS_CONFIRMED, $this->actor(), null);
    }

    public function test_cancelled_is_terminal(): void
    {
        $order = $this->order(Order::STATUS_CANCELLED);

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderService::class)->transition($order, Order::STATUS_CONFIRMED, $this->actor(), null);
    }
}
