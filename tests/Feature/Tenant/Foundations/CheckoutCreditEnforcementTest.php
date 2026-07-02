<?php

namespace Tests\Feature\Tenant\Foundations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Tests\TestCase;

class CheckoutCreditEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'address' => [
                'name'    => 'Test Bayi',
                'phone'   => '+90 555 555 55 55',
                'street'  => 'Test Mah. 1',
                'city'    => 'İstanbul',
            ],
            'shipping_method' => 'standard',
            'payment_method'  => 'bank',
            'terms_accepted'  => true,
        ];
    }

    public function test_tenant_user_over_credit_limit_is_blocked(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit'    => 100,
            'current_balance' => 80,   // 20 ₺ kullanılabilir
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        // Sepete yüksek fiyatlı ürün koy — CheckoutController price'ı cart_items.price'dan okur.
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Ürün', 'slug' => 'u-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 9999, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id'    => $user->id,
            'product_id' => $productId,
            'variant_id' => null,
            'color'      => null,
            'size'       => null,
            'qty'        => 1,
            'price'      => 9999.00,
        ]);

        $this->post(route('checkout.store'), $this->payload())
            ->assertRedirect(route('checkout.index'));

        // Hiç sipariş açılmadı + ledger boş.
        $this->assertSame(0, Order::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, TenantCreditLedger::query()->where('tenant_id', $tenant->id)->count());

        // Balance değişmedi.
        $this->assertEquals(80, (float) $tenant->fresh()->current_balance);
    }
}
