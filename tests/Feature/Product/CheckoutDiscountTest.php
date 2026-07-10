<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Tests\TestCase;

/**
 * Faz 3 / D4: checkout'ta sipariş düzeyi iskonto (tenant.discount_rate) ara toplama uygulanır;
 * kredi iskontolu total üstünden çekilir; payment_term_days > 0 ise due_date kurulur.
 */
class CheckoutDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function cart(int $userId, int $qty, float $price): \Illuminate\Support\Collection
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pid = DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => $price, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $userId, 'product_id' => $pid, 'variant_id' => null,
            'qty' => $qty, 'price' => $price, 'color' => null, 'size' => null,
        ]);

        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    public function test_order_level_discount_applied_and_credit_charged_on_discounted_total(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0,
            'discount_rate' => 10, 'payment_term_days' => 0,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250); // ara toplam 500 → ücretsiz kargo (>=500)

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'cargo'],
            $user->id, $tenant->id, $items,
            ['subtotal' => 500, 'total' => 500], // görüntü amaçlı — server yeniden hesaplar
        );

        $this->assertEqualsWithDelta(500.00, (float) $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(10.00, (float) $order->discount_rate, 0.01);
        $this->assertEqualsWithDelta(50.00, (float) $order->discount_amount, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $order->shipping_fee, 0.01);
        $this->assertEqualsWithDelta(450.00, (float) $order->total, 0.01);

        // Kredi iskontolu total (450) üstünden çekilir, ara toplam (500) değil.
        $this->assertEqualsWithDelta(450.00, (float) $tenant->fresh()->current_balance, 0.01);
        $this->assertSame(1, TenantCreditLedger::where('order_id', $order->id)->count());
    }

    public function test_due_date_set_from_payment_term_days(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0,
            'discount_rate' => 0, 'payment_term_days' => 30,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250);

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'cargo'], $user->id, $tenant->id, $items, [],
        );

        $this->assertNotNull($order->due_date);
        $this->assertSame(now()->addDays(30)->toDateString(), $order->due_date->toDateString());
    }

    public function test_no_due_date_when_payment_term_zero(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0,
            'discount_rate' => 0, 'payment_term_days' => 0,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250);

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'cargo'], $user->id, $tenant->id, $items, [],
        );

        $this->assertNull($order->due_date);
    }
}
