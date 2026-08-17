<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // StockChanged → PushStockToBagisto, QUEUE_CONNECTION=sync (phpunit.xml)
        // altında fake edilmeden gerçek bir HTTP isteğine çıkıyordu.
        Queue::fake();
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    private function cartFor(int $userId, ProductVariant $variant, int $qty, float $price = 100): \Illuminate\Support\Collection
    {
        CartItem::create([
            'user_id'    => $userId,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'color'      => $variant->color_name,
            'size'       => $variant->size,
            'qty'        => $qty,
            'price'      => $price,
        ]);

        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    public function test_checkout_decrements_default_warehouse_first_then_splits(): void
    {
        [$tenant, $user] = $this->tenantUser();

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
        $variant->update(['stock' => 15]);

        $items  = $this->cartFor($user->id, $variant, 8, 100);
        $totals = ['subtotal' => 800, 'shipping_fee' => 0, 'total' => 800, 'promo_code' => null];

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'standard', 'address' => []],
            $user->id,
            $tenant->id,
            $items,
            $totals,
        );

        // Varsayılan depo önce boşaltılır, kalan diğer depodan tahsis edilir.
        $this->assertSame(0, $stockDefault->fresh()->quantity);
        $this->assertSame(7, $stockOther->fresh()->quantity);

        // İki OUT hareketi, ikisi de Order'ı referans alır.
        $movements = StockMovement::where('product_variant_id', $variant->id)->get();
        $this->assertCount(2, $movements);
        foreach ($movements as $m) {
            $this->assertSame(StockMovement::TYPE_OUT, $m->type);
            $this->assertSame(Order::class, $m->reference_type);
            $this->assertSame($order->id, (int) $m->reference_id);
        }
        $this->assertEqualsCanonicalizing([-5, -3], $movements->pluck('quantity')->map(fn ($q) => (int) $q)->all());

        // Varyant cache resync: stocks toplamı = 0 + 7 = 7
        $this->assertSame(7, (int) $variant->fresh()->stock);
    }

    public function test_oversell_is_blocked_and_nothing_persists(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $product = Product::factory()->create(['price' => 100]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);
        $wh      = Warehouse::factory()->default()->create();
        $stock   = Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $wh->id, 'quantity' => 3,
        ]);

        $items  = $this->cartFor($user->id, $variant, 5, 100);
        $totals = ['subtotal' => 500, 'shipping_fee' => 0, 'total' => 500, 'promo_code' => null];

        try {
            app(CheckoutService::class)->place(
                ['shipping_method' => 'standard', 'address' => []],
                $user->id,
                $tenant->id,
                $items,
                $totals,
            );
            $this->fail('InsufficientStockException bekleniyordu.');
        } catch (InsufficientStockException $e) {
            $this->assertSame($variant->id, $e->variantId);
            $this->assertSame(5, $e->requested);
            $this->assertSame(3, $e->available);
        }

        // Hiçbir şey kalıcı olmadı: order yok, stok düşmedi, movement yok, kredi çekilmedi.
        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
        $this->assertSame(3, $stock->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());
        $this->assertEqualsWithDelta(0.0, (float) $tenant->fresh()->current_balance, 0.01);
    }

    public function test_available_for_variant_excludes_reserved(): void
    {
        $variant = ProductVariant::factory()->create();
        $wh      = Warehouse::factory()->create();
        Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $wh->id,
            'quantity' => 10, 'reserved_quantity' => 4,
        ]);

        $this->assertSame(6, app(\Modules\Product\Services\StockService::class)->availableForVariant($variant->id));
    }

    public function test_restock_mirrors_outs_and_is_idempotent(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $product = Product::factory()->create(['price' => 100]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);
        $wh      = Warehouse::factory()->default()->create();
        $stock   = Stock::factory()->create([
            'product_variant_id' => $variant->id, 'warehouse_id' => $wh->id, 'quantity' => 10,
        ]);

        $items = $this->cartFor($user->id, $variant, 4, 100);
        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'standard', 'address' => []],
            $user->id,
            $tenant->id,
            $items,
            ['subtotal' => 400, 'shipping_fee' => 0, 'total' => 400, 'promo_code' => null],
        );

        $this->assertSame(6, $stock->fresh()->quantity);

        $service = app(\Modules\Product\Services\StockService::class);
        $service->restockForOrder($order);

        // OUT'lar aynalandı: stok geri geldi.
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('reference_id', $order->id)->where('type', StockMovement::TYPE_IN)->count());

        // İkinci çağrı idempotent: yeni IN yazılmaz, stok değişmez.
        $service->restockForOrder($order);
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('reference_id', $order->id)->where('type', StockMovement::TYPE_IN)->count());
    }
}
