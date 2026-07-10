<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;
use Modules\Tenant\Models\TenantType;
use Tests\TestCase;

class CheckoutRepriceTest extends TestCase
{
    use RefreshDatabase;

    private function place(Tenant $tenant, User $user, ProductVariant $variant, float $stalePrice)
    {
        // Fiyat testleri için stok yeterli olmalı (aksi halde checkout stokta bloklanır).
        Stock::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => Warehouse::factory()->default()->create()->id,
            'quantity'           => 10,
        ]);

        CartItem::create([
            'user_id'    => $user->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'color'      => $variant->color_name,
            'size'       => $variant->size,
            'qty'        => 1,
            'price'      => $stalePrice, // BAYAT — checkout yok saymalı
        ]);
        $items = CartItem::with('product')->where('user_id', $user->id)->get();

        return app(CheckoutService::class)->place(
            ['shipping_method' => 'standard', 'address' => []],
            $user->id,
            $tenant->id,
            $items,
            ['subtotal' => $stalePrice, 'shipping_fee' => 0, 'total' => $stalePrice, 'promo_code' => null],
        );
    }

    public function test_stale_cart_price_is_ignored_falls_back_to_variant_price(): void
    {
        $tenant  = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0, 'tenant_type_id' => null]);
        $user    = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['price' => 500]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);

        $order = $this->place($tenant, $user, $variant, 9999.00);

        $line = $order->items()->first();
        $this->assertEqualsWithDelta(100.00, (float) $line->unit_price, 0.01);
    }

    public function test_custom_price_overrides_everything(): void
    {
        $type    = TenantType::create(['code' => 'dealer', 'name' => 'Bayi', 'price_list_type' => 'dealer', 'is_active' => true]);
        $tenant  = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0, 'tenant_type_id' => $type->id]);
        $user    = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['price' => 500]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);

        // price_list dealer = 90 (custom_price bunu da ezmeli)
        PriceList::create([
            'product_variant_id' => $variant->id, 'type' => 'dealer',
            'price' => 90, 'currency' => 'TRY', 'is_active' => true,
        ]);
        TenantProductAccess::create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id,
            'is_blocked' => false, 'custom_price' => 80,
        ]);

        $order = $this->place($tenant, $user, $variant, 9999.00);

        $this->assertEqualsWithDelta(80.00, (float) $order->items()->first()->unit_price, 0.01);
    }

    public function test_price_list_used_when_no_custom_price(): void
    {
        $type    = TenantType::create(['code' => 'dealer2', 'name' => 'Bayi', 'price_list_type' => 'dealer', 'is_active' => true]);
        $tenant  = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0, 'tenant_type_id' => $type->id]);
        $user    = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['price' => 500]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);

        PriceList::create([
            'product_variant_id' => $variant->id, 'type' => 'dealer',
            'price' => 90, 'currency' => 'TRY', 'is_active' => true,
        ]);

        $order = $this->place($tenant, $user, $variant, 9999.00);

        $this->assertEqualsWithDelta(90.00, (float) $order->items()->first()->unit_price, 0.01);
    }
}
