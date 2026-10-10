<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\VatRateService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * KDV: oran kategoriden (boşsa üstten miras, zincirde yoksa varsayılan); SaaS fiyatı KDV hariç,
 * mağazaya KDV dahil gider; oran değişince o kategori ağacındaki ürünler yeniden gönderilir.
 */
class VatRateTest extends TestCase
{
    use RefreshDatabase;

    private function category(?int $parentId = null, ?float $vat = null): Category
    {
        return Category::create([
            'name' => 'K '.uniqid(), 'slug' => 'k-'.uniqid(), 'parent_id' => $parentId,
            'status' => 'active', 'sort_order' => 0, 'vat_rate' => $vat,
        ]);
    }

    public function test_rate_is_inherited_from_ancestors_with_default_fallback(): void
    {
        config(['product.default_vat_rate' => 10]);
        $root = $this->category(vat: 20);
        $child = $this->category($root->id);
        $override = $this->category($child->id, 1);
        $orphan = $this->category();

        $vat = app(VatRateService::class);
        $this->assertSame(20.0, $vat->rateForCategory($child->id));
        $this->assertSame(1.0, $vat->rateForCategory($override->id));
        $this->assertSame(10.0, $vat->rateForCategory($orphan->id));
        $this->assertSame(10.0, $vat->rateForCategory(null));
    }

    public function test_category_form_saves_rate_and_store_price_is_gross(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'category.edit', 'guard_name' => 'web']);
        $user->givePermissionTo('category.edit');

        $root = $this->category();
        $child = $this->category($root->id);
        $product = Product::factory()->create(['category_id' => $child->id, 'status' => Product::STATUS_PUBLISHED]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);

        $this->actingAs($user)->put("/products/categories/{$root->id}", [
            'name' => $root->name, 'slug' => $root->slug, 'status' => 'active', 'sort_order' => 0, 'vat_rate' => 20,
        ])->assertSessionHasNoErrors();
        $this->assertSame('20.00', $root->fresh()->vat_rate);

        // Alt kategorideki ürün yeniden gönderilir ve KDV dahil (%20) gider
        Queue::assertPushed(PushProductToBagisto::class, fn ($job) => (fn () => $this->productId)->call($job) === $product->id);
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
        $this->assertSame(120.0, $payload['variants'][0]['price']);
        $this->assertSame('kdv-20', $payload['tax_category_code']);

        // Bagisto'da vergi kategorisi olmayan oran kabul edilmez; %0 = vergi kategorisi yok
        $this->actingAs($user)->put("/products/categories/{$root->id}", [
            'name' => $root->name, 'slug' => $root->slug, 'status' => 'active', 'sort_order' => 0, 'vat_rate' => 18,
        ])->assertSessionHasErrors('vat_rate');
        $root->update(['vat_rate' => 0]);
        app(VatRateService::class)->flush();
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
        $this->assertSame(['', 100.0], [$payload['tax_category_code'], $payload['variants'][0]['price']]);
    }
}
