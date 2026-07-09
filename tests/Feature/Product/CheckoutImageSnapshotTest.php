<?php

namespace Tests\Feature\Product;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class CheckoutImageSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_item_snapshots_real_cover_image_path(): void
    {
        $tenant  = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);
        $user    = User::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['price' => 100]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);
        Stock::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => Warehouse::factory()->default()->create()->id,
            'quantity'           => 10,
        ]);

        // Kapak olmayan görsel + kapak görseli — kapak seçilmeli.
        ProductImage::create([
            'product_id' => $product->id, 'path' => 'products/' . $product->id . '/alt.jpg',
            'sort_order' => 0, 'is_cover' => false,
        ]);
        ProductImage::create([
            'product_id' => $product->id, 'path' => 'products/' . $product->id . '/cover.jpg',
            'sort_order' => 1, 'is_cover' => true,
        ]);

        CartItem::create([
            'user_id' => $user->id, 'product_id' => $product->id, 'variant_id' => $variant->id,
            'color' => $variant->color_name, 'size' => $variant->size, 'qty' => 1, 'price' => 100,
        ]);
        $items = CartItem::with('product')->where('user_id', $user->id)->get();

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'standard', 'address' => []],
            $user->id,
            $tenant->id,
            $items,
            ['subtotal' => 100, 'shipping_fee' => 0, 'total' => 100, 'promo_code' => null],
        );

        $line = $order->items()->first();

        // Ham path DB'de; picsum yok.
        $this->assertSame('products/' . $product->id . '/cover.jpg', $line->product_image);
        $this->assertStringNotContainsString('picsum', (string) $line->product_image);

        // Accessor Media::url() ile tam URL üretir.
        $this->assertSame(Media::url('products/' . $product->id . '/cover.jpg'), $line->product_image_url);
    }
}
