<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Checkout sayfasındaki sepet düzenleme (adet güncelle / kalem çıkar) uçtan uca.
 * Bu, "ödeme yapma imkanı"nı tamamlar: tenant min-sipariş/koli katı kurallarını
 * sepette düzeltip siparişi tamamlayabilir. Backend rotaları paylaşımlı (/cart/*).
 */
class PortalCartEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.checkout', 'portal.catalog.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.checkout', 'portal.catalog.view']);
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'cart-' . uniqid(), 'credit_limit' => 10000, 'current_balance' => 0, 'discount_rate' => 0,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        return [$tenant, $user];
    }

    private function product(float $price, ?int $minOrderQty = null): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'name' => 'Ürün', 'slug' => 'u-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => $price, 'category_id' => $categoryId, 'min_order_qty' => $minOrderQty,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_updating_quantity_reprices_checkout(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $productId = $this->product(150, minOrderQty: 6);
        $item = CartItem::create([
            'user_id' => $user->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 2, 'price' => 150, 'color' => null, 'size' => null,
        ]);

        // Adeti min-sipariş kuralını sağlayacak şekilde 6'ya çek.
        $this->actingAs($user)
            ->put("http://{$tenant->slug}.bizimsite.test/cart/{$item->id}", ['qty' => 6])
            ->assertRedirect();

        $this->assertSame(6, (int) $item->fresh()->qty);

        // Checkout sayfası yeni adet ve toplamı yansıtır; UI'nin ihtiyaç duyduğu
        // düzenleme alanları (qty, min_order_qty) payload'da var.
        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$tenant->slug}.bizimsite.test/checkout")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Tenant::Portal/Checkout')
                ->where('items.0.qty', 6)
                ->where('items.0.min_order_qty', 6)
                ->where('totals.subtotal', fn ($v) => (float) $v === 900.0)
            );
    }

    public function test_removing_last_item_empties_cart_and_redirects_to_catalog(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $productId = $this->product(150);
        $item = CartItem::create([
            'user_id' => $user->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 1, 'price' => 150, 'color' => null, 'size' => null,
        ]);

        $this->actingAs($user)
            ->delete("http://{$tenant->slug}.bizimsite.test/cart/{$item->id}")
            ->assertRedirect();

        $this->assertSame(0, CartItem::where('user_id', $user->id)->count());

        // Sepet boş → checkout katalog'a yönlendirir.
        $this->actingAs($user)
            ->get("http://{$tenant->slug}.bizimsite.test/checkout")
            ->assertRedirect('/catalog');
    }

    public function test_cannot_edit_another_users_cart_item(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $other = User::factory()->create(['tenant_id' => $tenant->id]);
        $other->assignRole('tenant');
        $productId = $this->product(150);
        $item = CartItem::create([
            'user_id' => $other->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 1, 'price' => 150, 'color' => null, 'size' => null,
        ]);

        $this->actingAs($user)
            ->put("http://{$tenant->slug}.bizimsite.test/cart/{$item->id}", ['qty' => 3])
            ->assertForbidden();

        $this->assertSame(1, (int) $item->fresh()->qty);
    }
}
