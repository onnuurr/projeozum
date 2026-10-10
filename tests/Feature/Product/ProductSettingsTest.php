<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Product Ayarları: tek sayfa (product-settings.view) + her sekme kendi yetkisiyle.
 * Bedenler/Renkler sekmeleri (ürün formu varyant listeleri) — her işlem ayrı yetki:
 * variant-option.view / .create / .update / .delete.
 */
class ProductSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    public function test_migration_seeds_previous_default_lists(): void
    {
        $this->assertSame(15, ProductSize::count());
        $this->assertSame(12, ProductColor::count());
        $this->assertSame('#1e3a8a', ProductColor::where('name', 'Lacivert')->value('hex'));
        // Sıra 1'den başlar (kategorilerle aynı düzen)
        $this->assertSame(range(1, 15), ProductSize::orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame(range(1, 12), ProductColor::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_page_requires_settings_permission_and_tabs_require_their_own(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user(['variant-option.view']))->get('/products/settings')->assertForbidden();

        // Sayfa yetkisi var ama hiçbir sekme yetkisi yok → sayfa yokmuş gibi 403
        $this->actingAs($this->user(['product-settings.view']))->get('/products/settings')->assertForbidden();

        $this->actingAs($this->user(['product-settings.view', 'variant-option.view']))->get('/products/settings?tab=colors')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'colors')
                ->has('sizes', 15)
                ->has('colors', 12));
    }

    public function test_settings_gear_is_shared_only_with_settings_permission(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user(['product.view']))->get('/products')
            ->assertInertia(fn ($page) => $page->where('moduleSettings', []));

        // Sayfa yetkisi tek başına yetmez: görebileceği hiçbir sekme yoksa çark da yok
        $this->actingAs($this->user(['product.view', 'product-settings.view']))->get('/products')
            ->assertInertia(fn ($page) => $page->where('moduleSettings', []));

        $this->actingAs($this->user(['product.view', 'product-settings.view', 'variant-option.view']))->get('/products')
            ->assertInertia(fn ($page) => $page
                ->where('moduleSettings.0.key', 'product')
                ->where('moduleSettings.0.url', '/products/settings'));
    }

    public function test_each_write_action_has_its_own_permission(): void
    {
        $viewer = $this->user(['variant-option.view']);

        $this->actingAs($viewer)->post('/products/settings/sizes', ['name' => '3XL'])->assertForbidden();
        $this->actingAs($viewer)->post('/products/settings/colors', ['name' => 'Haki', 'hex' => '#6b6b3a'])->assertForbidden();

        $creator = $this->user(['variant-option.create']);
        $this->actingAs($creator)->post('/products/settings/sizes', ['name' => ' 3XL '])->assertRedirect();
        $this->actingAs($creator)->post('/products/settings/colors', ['name' => 'Haki', 'hex' => '#6B6B3A'])->assertRedirect();

        $size = ProductSize::where('name', '3XL')->firstOrFail();
        $color = ProductColor::where('name', 'Haki')->firstOrFail();
        $this->assertSame('#6b6b3a', $color->hex);
        $this->assertSame(16, $size->sort_order); // 1, 2, 3… düzeninde listenin sonu

        // create yetkisi düzenleme/silme vermez
        $this->actingAs($creator)->put("/products/settings/sizes/{$size->id}", ['name' => 'XXXL'])->assertForbidden();
        $this->actingAs($creator)->delete("/products/settings/colors/{$color->id}")->assertForbidden();

        $updater = $this->user(['variant-option.update']);
        $this->actingAs($updater)->put("/products/settings/sizes/{$size->id}", ['name' => 'XXXL'])->assertRedirect();
        $this->assertSame('XXXL', $size->fresh()->name);
        $this->actingAs($updater)->delete("/products/settings/sizes/{$size->id}")->assertForbidden();

        $deleter = $this->user(['variant-option.delete']);
        $this->actingAs($deleter)->delete("/products/settings/sizes/{$size->id}")->assertRedirect();
        $this->actingAs($deleter)->delete("/products/settings/colors/{$color->id}")->assertRedirect();
        $this->assertNull($size->fresh());
        $this->assertNull($color->fresh());
    }

    public function test_bulk_destroy_requires_delete_permission_and_targets_right_table(): void
    {
        $sizeIds = ProductSize::whereIn('name', ['XS', 'S'])->pluck('id')->all();
        $colorIds = ProductColor::whereIn('name', ['Mor', 'Pembe'])->pluck('id')->all();

        $this->actingAs($this->user(['variant-option.update']))
            ->post('/products/settings/sizes/bulk-destroy', ['ids' => $sizeIds])->assertForbidden();

        $deleter = $this->user(['variant-option.delete']);
        $this->actingAs($deleter)->post('/products/settings/sizes/bulk-destroy', ['ids' => $sizeIds])
            ->assertRedirect('/products/settings?tab=sizes');
        $this->actingAs($deleter)->post('/products/settings/colors/bulk-destroy', ['ids' => $colorIds])
            ->assertRedirect('/products/settings?tab=colors');

        $this->assertSame(13, ProductSize::count());
        $this->assertSame(10, ProductColor::count());

        // Renk id'leri beden tablosunda doğrulanır; geçersiz tip rotaya hiç eşleşmez
        $this->actingAs($deleter)->post('/products/settings/sizes/bulk-destroy', ['ids' => [999999]])->assertSessionHasErrors('ids.0');
        $this->actingAs($deleter)->post('/products/settings/brands/bulk-destroy', ['ids' => [1]])->assertNotFound();
    }

    public function test_bulk_store_adds_items_in_order_and_requires_create_permission(): void
    {
        $sizes = ['items' => [['name' => ' 4XL '], ['name' => '5XL'], ['name' => '6XL']]];

        $this->actingAs($this->user(['variant-option.update']))
            ->post('/products/settings/sizes/bulk-store', $sizes)->assertForbidden();

        $creator = $this->user(['variant-option.create']);
        $this->actingAs($creator)->post('/products/settings/sizes/bulk-store', $sizes)
            ->assertRedirect('/products/settings?tab=sizes');
        $this->actingAs($creator)->post('/products/settings/colors/bulk-store', ['items' => [
            ['name' => 'Haki', 'hex' => '#6B6B3A'],
            ['name' => 'Vizon', 'hex' => '#a39081'],
        ]])->assertRedirect('/products/settings?tab=colors');

        // Girilen sırayla listenin sonuna (15 seed beden, 12 seed renk)
        $this->assertSame(['4XL' => 16, '5XL' => 17, '6XL' => 18], ProductSize::where('sort_order', '>', 15)->orderBy('sort_order')->pluck('sort_order', 'name')->all());
        $this->assertSame('#6b6b3a', ProductColor::where('name', 'Haki')->value('hex'));
        $this->assertSame(14, ProductColor::where('name', 'Vizon')->value('sort_order'));
    }

    public function test_bulk_store_is_all_or_nothing_and_validates_each_item(): void
    {
        $creator = $this->user(['variant-option.create']);

        // Mevcut ad (M) ve liste içi tekrar → hiçbiri eklenmez, hata ilgili satırda
        $this->actingAs($creator)->post('/products/settings/sizes/bulk-store', ['items' => [['name' => '7XL'], ['name' => 'M']]])
            ->assertSessionHasErrors('items.1.name');
        $this->actingAs($creator)->post('/products/settings/sizes/bulk-store', ['items' => [['name' => '7XL'], ['name' => '7xl']]])
            ->assertSessionHasErrors('items.1.name');
        $this->assertSame(15, ProductSize::count());

        $this->actingAs($creator)->post('/products/settings/colors/bulk-store', ['items' => [['name' => 'Haki', 'hex' => 'yeşil']]])
            ->assertSessionHasErrors('items.0.hex');
        $this->actingAs($creator)->post('/products/settings/colors/bulk-store', ['items' => []])->assertSessionHasErrors('items');
        $this->assertSame(12, ProductColor::count());
    }

    public function test_validation_rejects_duplicates_and_bad_hex(): void
    {
        $creator = $this->user(['variant-option.create']);

        $this->actingAs($creator)->post('/products/settings/sizes', ['name' => 'M'])->assertSessionHasErrors('name');
        $this->actingAs($creator)->post('/products/settings/colors', ['name' => 'Siyah', 'hex' => '#000000'])->assertSessionHasErrors('name');
        $this->actingAs($creator)->post('/products/settings/colors', ['name' => 'Haki', 'hex' => 'yeşil'])->assertSessionHasErrors('hex');
    }

    public function test_product_form_receives_options_from_database(): void
    {
        ProductSize::create(['name' => '5XL', 'sort_order' => 999]);

        $this->actingAs($this->user(['product.view', 'product.add']))
            ->get('/products/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Product::ProductForm', false)
                ->where('sizeOptions', fn ($sizes) => collect($sizes)->last() === '5XL')
                ->has('colorOptions', 12));
    }
}
