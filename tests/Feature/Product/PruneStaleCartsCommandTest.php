<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Tests\TestCase;

/**
 * Faz 4: product:prune-stale-carts 30 günden eski terk edilmiş sepet kalemlerini siler,
 * güncel olanları korur (UX hijyeni — stok/fiyat place()'te çözülür).
 */
class PruneStaleCartsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCartItem(int $userId, int $productId): CartItem
    {
        return CartItem::create([
            'user_id' => $userId, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 1, 'price' => 100, 'color' => null, 'size' => null,
        ]);
    }

    private function product(): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 100, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_prunes_items_older_than_threshold_and_keeps_fresh(): void
    {
        $user = User::factory()->create();
        $pid  = $this->product();

        $stale = $this->makeCartItem($user->id, $pid);
        $fresh = $this->makeCartItem($user->id, $pid);

        // stale'i 40 gün öncesine taşı (timestamps'i bypass ederek).
        DB::table('cart_items')->where('id', $stale->id)->update(['updated_at' => now()->subDays(40)]);

        $this->artisan('product:prune-stale-carts')->assertSuccessful();

        $this->assertDatabaseMissing('cart_items', ['id' => $stale->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $fresh->id]);
    }

    public function test_days_option_is_respected(): void
    {
        $user = User::factory()->create();
        $pid  = $this->product();
        $item = $this->makeCartItem($user->id, $pid);

        DB::table('cart_items')->where('id', $item->id)->update(['updated_at' => now()->subDays(10)]);

        // 30 günde silinmez...
        $this->artisan('product:prune-stale-carts')->assertSuccessful();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id]);

        // ...ama --days=7 ile silinir.
        $this->artisan('product:prune-stale-carts', ['--days' => 7])->assertSuccessful();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }
}
