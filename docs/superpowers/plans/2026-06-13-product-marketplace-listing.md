# Ürün–Pazaryeri Listeleme Paneli Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ürün Kataloğu tablosunda bir pazaryeri logosuna tıklanınca, o ürünün ilgili pazaryerindeki listeleme kaydını (gönderim durumu, başlık, model kodu, fiyat, varyant eşleştirme vb.) gösteren, düzenleyip kaydedilebilen bir sağ-drawer paneli eklemek.

**Architecture:** Yeni `product_marketplace_listings` tablosu (varyantlar JSON kolonda) + `ProductMarketplaceListing` modeli. `ProductMarketplaceListingController` JSON `show`/`upsert` uçları sağlar; drawer (Vue + axios) bunları tüketir. `ProductController@index` her ürüne listeleme özetini (fiyat + gönderildi mi) ekler. Gerçek pazaryeri API gönderimi kapsam dışı — `is_sent` yerel bayraktır.

**Tech Stack:** Laravel 11 (PostgreSQL), nwidart modüller, Inertia + Vue 3, axios, PHPUnit 11 (class-based, RefreshDatabase, Spatie RBAC), Vite.

**Spec:** `docs/superpowers/specs/2026-06-13-product-marketplace-listing-design.md`

---

## File Structure

- **Create** `Modules/Product/database/migrations/2026_06_13_120000_create_product_marketplace_listings_table.php` — tablo şeması.
- **Create** `Modules/Product/Models/ProductMarketplaceListing.php` — model.
- **Modify** `Modules/Product/Models/Product.php` — `listings()` ilişkisi.
- **Create** `Modules/Product/Http/Controllers/ProductMarketplaceListingController.php` — `show` + `upsert`.
- **Modify** `Modules/Product/routes/web.php` — iki rota (slug catch-all'dan önce).
- **Modify** `Modules/Product/Http/Controllers/ProductController.php` — index payload'una `listings` özeti.
- **Create** `Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue` — drawer bileşeni.
- **Modify** `Modules/Product/Resources/assets/js/Pages/Products.vue` — logo tıklama + drawer + özet güncelleme.
- **Create** `tests/Feature/Product/MarketplaceListingTest.php` — backend feature testleri.

---

## Task 1: Migration + Model + Product ilişkisi

**Files:**
- Create: `Modules/Product/database/migrations/2026_06_13_120000_create_product_marketplace_listings_table.php`
- Create: `Modules/Product/Models/ProductMarketplaceListing.php`
- Modify: `Modules/Product/Models/Product.php`

- [ ] **Step 1: Migration dosyasını yaz**

`Modules/Product/database/migrations/2026_06_13_120000_create_product_marketplace_listings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('product_status', 16)->default('active');
            $table->string('approval_status', 16)->default('not_sent');
            $table->string('store_name')->nullable();
            $table->string('model_code', 64)->nullable();
            $table->string('category_path')->nullable();
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 8)->default('TL');
            $table->decimal('variant_extra_price', 12, 2)->default(0);
            $table->string('delivery_template')->nullable();
            $table->unsignedInteger('shipping_time')->nullable();
            $table->json('variants')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'marketplace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_marketplace_listings');
    }
};
```

- [ ] **Step 2: Migration'ı çalıştır**

Run: `php artisan migrate --force`
Expected: `2026_06_13_120000_create_product_marketplace_listings_table ... DONE`

- [ ] **Step 3: Model dosyasını yaz**

`Modules/Product/Models/ProductMarketplaceListing.php`:

```php
<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMarketplaceListing extends Model
{
    protected $table = 'product_marketplace_listings';

    protected $fillable = [
        'product_id',
        'marketplace_id',
        'is_sent',
        'sent_at',
        'product_status',
        'approval_status',
        'store_name',
        'model_code',
        'category_path',
        'title',
        'price',
        'currency',
        'variant_extra_price',
        'delivery_template',
        'shipping_time',
        'variants',
    ];

    protected $casts = [
        'is_sent'             => 'boolean',
        'sent_at'             => 'datetime',
        'price'               => 'decimal:2',
        'variant_extra_price' => 'decimal:2',
        'shipping_time'       => 'integer',
        'variants'            => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }
}
```

- [ ] **Step 4: Product modeline `listings()` ilişkisi ekle**

`Modules/Product/Models/Product.php` — mevcut `images()` ilişkisinden sonra ekle:

```php
    public function listings(): HasMany
    {
        return $this->hasMany(ProductMarketplaceListing::class);
    }
```

(`HasMany` zaten import edilmiş — dosyada `use Illuminate\Database\Eloquent\Relations\HasMany;` var.)

- [ ] **Step 5: PHP sözdizimi doğrula**

Run: `php -l Modules/Product/Models/ProductMarketplaceListing.php && php -l Modules/Product/Models/Product.php`
Expected: `No syntax errors detected` (her iki dosya)

- [ ] **Step 6: Commit**

```bash
git add Modules/Product/database/migrations/2026_06_13_120000_create_product_marketplace_listings_table.php Modules/Product/Models/ProductMarketplaceListing.php Modules/Product/Models/Product.php
git commit -m "feat(product): pazaryeri listeleme tablosu + modeli"
```

---

## Task 2: Controller `show` + rotalar (TDD)

**Files:**
- Create: `Modules/Product/Http/Controllers/ProductMarketplaceListingController.php`
- Modify: `Modules/Product/routes/web.php`
- Test: `tests/Feature/Product/MarketplaceListingTest.php`

- [ ] **Step 1: Failing test yaz (show)**

`tests/Feature/Product/MarketplaceListingTest.php`:

```php
<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Süveter',
            'sku' => 'SKU-' . uniqid(),
            'gender' => 'Unisex',
            'price' => 107.88,
        ]);

        $product->variants()->create([
            'sku' => 'V-' . uniqid(), 'price' => 107.88, 'stock' => 5, 'sort_order' => 0,
        ]);

        return $product;
    }

    public function test_show_returns_default_draft_when_no_listing_exists(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $this->actingAs($this->admin)
            ->getJson("/products/{$product->id}/marketplaces/n11/listing")
            ->assertOk()
            ->assertJsonPath('listing.is_sent', false)
            ->assertJsonPath('listing.approval_status', 'not_sent')
            ->assertJsonPath('listing.title', 'Test Süveter')
            ->assertJsonPath('marketplace.key', 'n11')
            ->assertJsonCount(1, 'variants');
    }

    public function test_show_returns_404_for_unknown_marketplace(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)
            ->getJson("/products/{$product->id}/marketplaces/bilinmeyen/listing")
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Testi çalıştır, fail ettiğini gör**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: FAIL (rota/controller yok → 404/500)

- [ ] **Step 3: Controller'ı `show` ile yaz**

`Modules/Product/Http/Controllers/ProductMarketplaceListingController.php`:

```php
<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductMarketplaceListing;

class ProductMarketplaceListingController extends Controller
{
    public function show(Product $product, string $marketplace): JsonResponse
    {
        $mp = Marketplace::where('key', $marketplace)->firstOrFail();

        $product->load([
            'category:id,name,slug,parent_id',
            'category.parent:id,name',
            'brand:id,name',
            'variants',
            'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
        ]);

        $listing = ProductMarketplaceListing::query()
            ->where('product_id', $product->id)
            ->where('marketplace_id', $mp->id)
            ->first();

        $productVariants = $product->variants->map(fn ($v) => [
            'id'    => $v->id,
            'label' => $this->variantLabel($v),
            'sku'   => $v->sku,
        ])->values()->all();

        return response()->json([
            'marketplace' => ['key' => $mp->key, 'name' => $mp->name],
            'product'     => [
                'id'          => $product->id,
                'name'        => $product->name,
                'brand'       => $product->brand?->name,
                'categoryPath' => $this->categoryPath($product),
                'price'       => (float) $product->price,
                'marketPrice' => $product->market_price !== null ? (float) $product->market_price : null,
                'status'      => 'Aktif',
                'image'       => optional($product->images->first())->url,
                'editUrl'     => "/products/{$product->id}/edit",
            ],
            'variants' => $productVariants,
            'listing'  => $this->shapeListing($listing, $product, $productVariants),
        ]);
    }

    private function shapeListing(?ProductMarketplaceListing $listing, Product $product, array $productVariants): array
    {
        $storedVariants = collect($listing?->variants ?? []);

        $variants = collect($productVariants)->map(function ($pv) use ($storedVariants) {
            $stored = $storedVariants->firstWhere('product_variant_id', $pv['id']) ?? [];
            return [
                'product_variant_id' => $pv['id'],
                'marketplace_variant' => $stored['marketplace_variant'] ?? '',
                'stock_code'          => $stored['stock_code'] ?? '',
                'barcode'             => $stored['barcode'] ?? '',
            ];
        })->values()->all();

        return [
            'id'                  => $listing?->id,
            'is_sent'             => (bool) ($listing?->is_sent ?? false),
            'sent_at'             => $listing?->sent_at?->toIso8601String(),
            'product_status'      => $listing?->product_status ?? 'active',
            'approval_status'     => $listing?->approval_status ?? 'not_sent',
            'store_name'          => $listing?->store_name ?? '',
            'model_code'          => $listing?->model_code ?? '',
            'category_path'       => $listing?->category_path ?? '',
            'title'               => $listing?->title ?? $product->name,
            'price'               => (float) ($listing?->price ?? $product->price),
            'currency'            => $listing?->currency ?? 'TL',
            'variant_extra_price' => (float) ($listing?->variant_extra_price ?? 0),
            'delivery_template'   => $listing?->delivery_template ?? '',
            'shipping_time'       => $listing?->shipping_time ?? 0,
            'variants'            => $variants,
        ];
    }

    private function variantLabel($v): string
    {
        $parts = array_filter([
            $v->color_name ? "Renk: {$v->color_name}" : null,
            $v->size ? "Beden: {$v->size}" : null,
        ]);
        return $parts === [] ? ($v->sku ?? '—') : implode(' / ', $parts);
    }

    private function categoryPath(Product $product): ?string
    {
        $cat = $product->category;
        if (! $cat) {
            return null;
        }
        return $cat->parent
            ? "{$cat->parent->name} > {$cat->name}"
            : $cat->name;
    }
}
```

- [ ] **Step 4: Rotaları ekle**

`Modules/Product/routes/web.php` — `Route::post('/products/marketplaces/{marketplace}/connect', ...)` satırından hemen sonra, slug catch-all'dan **önce** ekle:

```php
    // ─── Ürün-Pazaryeri Listeleme ────────────────────────────────────────
    Route::get('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Product\Http\Controllers\ProductMarketplaceListingController::class, 'show'])
        ->whereNumber('product')
        ->name('products.listings.show');
    Route::put('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Product\Http\Controllers\ProductMarketplaceListingController::class, 'upsert'])
        ->whereNumber('product')
        ->middleware('can:product.add')
        ->name('products.listings.upsert');
```

- [ ] **Step 5: Testi çalıştır, geçtiğini gör**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: `test_show_returns_default_draft_when_no_listing_exists` ve `test_show_returns_404_for_unknown_marketplace` PASS (upsert testi henüz yok).

- [ ] **Step 6: Commit**

```bash
git add Modules/Product/Http/Controllers/ProductMarketplaceListingController.php Modules/Product/routes/web.php tests/Feature/Product/MarketplaceListingTest.php
git commit -m "feat(product): listeleme show ucu + rotalar"
```

---

## Task 3: Controller `upsert` (TDD)

**Files:**
- Modify: `Modules/Product/Http/Controllers/ProductMarketplaceListingController.php`
- Test: `tests/Feature/Product/MarketplaceListingTest.php`

- [ ] **Step 1: Failing testleri ekle (upsert + yetki)**

`tests/Feature/Product/MarketplaceListingTest.php` sınıfına ekle:

```php
    public function test_upsert_creates_listing_and_marks_sent(): void
    {
        $product = $this->makeProduct();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        $variantId = $product->variants->first()->id;

        $this->actingAs($this->admin)
            ->putJson("/products/{$product->id}/marketplaces/n11/listing", [
                'product_status' => 'active',
                'store_name' => '#1 - TEST MAĞAZA',
                'model_code' => 'P3946S523',
                'title' => 'Şah Desenli Triko Süveter',
                'price' => 115.87,
                'currency' => 'TL',
                'variant_extra_price' => 0,
                'shipping_time' => 2,
                'variants' => [
                    ['product_variant_id' => $variantId, 'marketplace_variant' => 'Std', 'stock_code' => 'SK1', 'barcode' => '8690000000001'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('listing.isSent', true)
            ->assertJsonPath('listing.price', 115.87);

        $this->assertDatabaseHas('product_marketplace_listings', [
            'product_id' => $product->id,
            'marketplace_id' => $mp->id,
            'is_sent' => true,
            'model_code' => 'P3946S523',
            'approval_status' => 'pending',
        ]);
    }

    public function test_upsert_requires_permission(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $plainUser = User::factory()->create(); // rolsüz → yetkisiz

        $this->actingAs($plainUser)
            ->putJson("/products/{$product->id}/marketplaces/n11/listing", [
                'product_status' => 'active', 'title' => 'X', 'price' => 10, 'shipping_time' => 1,
            ])
            ->assertForbidden();
    }
```

- [ ] **Step 2: Testleri çalıştır, fail ettiğini gör**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: yeni iki test FAIL (`upsert` metodu yok → 500/405).

- [ ] **Step 3: `upsert` metodunu controller'a ekle**

`ProductMarketplaceListingController` içine `show`'dan sonra ekle:

```php
    public function upsert(Request $request, Product $product, string $marketplace): JsonResponse
    {
        $mp = Marketplace::where('key', $marketplace)->firstOrFail();

        $data = $request->validate([
            'product_status'      => ['required', 'in:active,passive'],
            'store_name'          => ['nullable', 'string', 'max:191'],
            'model_code'          => ['nullable', 'string', 'max:64'],
            'category_path'       => ['nullable', 'string', 'max:191'],
            'title'               => ['nullable', 'string', 'max:191'],
            'price'               => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'max:8'],
            'variant_extra_price' => ['nullable', 'numeric', 'min:0'],
            'delivery_template'   => ['nullable', 'string', 'max:191'],
            'shipping_time'       => ['nullable', 'integer', 'min:0'],
            'variants'                          => ['nullable', 'array'],
            'variants.*.product_variant_id'     => ['required', 'integer'],
            'variants.*.marketplace_variant'    => ['nullable', 'string', 'max:191'],
            'variants.*.stock_code'             => ['nullable', 'string', 'max:64'],
            'variants.*.barcode'                => ['nullable', 'string', 'max:64'],
        ]);

        $existing = ProductMarketplaceListing::query()
            ->where('product_id', $product->id)
            ->where('marketplace_id', $mp->id)
            ->first();

        $approval = $existing?->approval_status ?? 'not_sent';
        if ($approval === 'not_sent') {
            $approval = 'pending';
        }

        $listing = ProductMarketplaceListing::updateOrCreate(
            ['product_id' => $product->id, 'marketplace_id' => $mp->id],
            array_merge($data, [
                'is_sent'         => true,
                'sent_at'         => now(),
                'approval_status' => $approval,
                'variants'        => $data['variants'] ?? [],
            ]),
        );

        return response()->json([
            'ok' => true,
            'listing' => [
                'price'  => (float) $listing->price,
                'isSent' => (bool) $listing->is_sent,
            ],
        ]);
    }
```

- [ ] **Step 4: Testleri çalıştır, geçtiğini gör**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: 4 test PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Product/Http/Controllers/ProductMarketplaceListingController.php tests/Feature/Product/MarketplaceListingTest.php
git commit -m "feat(product): listeleme upsert ucu + yetki testi"
```

---

## Task 4: `ProductController@index` listeleme özeti

**Files:**
- Modify: `Modules/Product/Http/Controllers/ProductController.php`

- [ ] **Step 1: Eager-load'a `listings.marketplace` ekle**

`ProductController::index()` içindeki `->with([...])` dizisine ekle (mevcut `'images' => ...` satırından sonra):

```php
                'listings.marketplace:id,key',
```

- [ ] **Step 2: Ürün payload'una `listings` özeti ekle**

`index()` map'inde, `'marketplaces' => ...` bloğundan hemen sonra ekle:

```php
                    'listings'          => $p->listings->mapWithKeys(fn ($l) => [
                        $l->marketplace->key => [
                            'price'  => $l->price !== null ? (float) $l->price : null,
                            'isSent' => (bool) $l->is_sent,
                        ],
                    ])->all(),
```

- [ ] **Step 3: PHP sözdizimi doğrula**

Run: `php -l Modules/Product/Http/Controllers/ProductController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Mevcut testlerin hâlâ geçtiğini doğrula**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: 4 test PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Product/Http/Controllers/ProductController.php
git commit -m "feat(product): katalog payload'una listeleme ozeti"
```

---

## Task 5: `MarketplaceListingDrawer.vue` bileşeni

**Files:**
- Create: `Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue`

- [ ] **Step 1: Drawer bileşenini yaz**

`Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue`:

```vue
<template>
	<teleport to="body">
		<div v-if="open" class="lst-overlay" @click.self="close">
			<aside class="lst-drawer" role="dialog" aria-modal="true">
				<header class="lst-head">
					<div class="lst-title">
						<span class="lst-mp-badge" :style="{ background: marketplace?.color || '#888' }">{{ marketplace?.logoText }}</span>
						<h2>{{ marketplace?.name }} — Yeni Listeleme</h2>
					</div>
					<button class="lst-close" @click="close" aria-label="Kapat">✕</button>
				</header>

				<div v-if="loading" class="lst-loading">Yükleniyor…</div>

				<div v-else-if="form" class="lst-body">
					<div class="lst-alert" :class="form.is_sent ? 'ok' : 'warn'">
						{{ form.is_sent ? `Ürün ${marketplace.name} pazaryerine gönderildi.` : `Ürün ${marketplace.name} pazaryerine gönderilmedi.` }}
					</div>

					<div class="lst-grid">
						<div class="lst-main">
							<div class="form-grid-2">
								<div class="form-group">
									<label class="form-label">Mağaza</label>
									<input v-model="form.store_name" class="form-input" type="text" placeholder="örn. #1 - TEST MAĞAZA" />
								</div>
								<div class="form-group">
									<label class="form-label">Ürün Durumu</label>
									<CustomSelect v-model="form.product_status" :options="statusOptions" :show-label="false" />
								</div>
							</div>

							<div class="form-grid-2">
								<div class="form-group">
									<label class="form-label">{{ marketplace.name }} Onay Durumu</label>
									<div class="lst-readonly">{{ approvalLabel }}</div>
								</div>
								<div class="form-group">
									<label class="form-label">Model Kodu</label>
									<input v-model="form.model_code" class="form-input" type="text" maxlength="64" />
								</div>
							</div>

							<div class="form-group">
								<label class="form-label">Kategori</label>
								<input v-model="form.category_path" class="form-input" type="text" placeholder="Pazaryeri kategorisi" />
							</div>

							<div class="form-group">
								<label class="form-label">Başlık</label>
								<input v-model="form.title" class="form-input" type="text" :placeholder="product.name" maxlength="191" />
							</div>

							<div class="form-grid-3">
								<div class="form-group">
									<label class="form-label">Fiyat</label>
									<div class="lst-price-row">
										<input v-model.number="form.price" class="form-input" type="number" step="0.01" min="0" />
										<CustomSelect v-model="form.currency" :options="currencyOptions" :show-label="false" />
									</div>
								</div>
								<div class="form-group">
									<label class="form-label">Varyant Ek Fiyat</label>
									<input v-model.number="form.variant_extra_price" class="form-input" type="number" step="0.01" min="0" />
								</div>
								<div class="form-group">
									<label class="form-label">Sevkiyat Süresi (gün)</label>
									<input v-model.number="form.shipping_time" class="form-input" type="number" min="0" />
								</div>
							</div>

							<div class="form-group">
								<label class="form-label">Teslimat Şablonu</label>
								<input v-model="form.delivery_template" class="form-input" type="text" placeholder="Hiçbiri seçilmedi" />
							</div>

							<div class="lst-variants">
								<div class="lst-variants-head">Listeleme Varyant Bilgileri</div>
								<table class="lst-table">
									<thead>
										<tr>
											<th>Ürün Varyantları</th>
											<th>{{ marketplace.name }} Varyantları</th>
											<th>Stok Kodu</th>
											<th>Barkod</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="(row, i) in form.variants" :key="row.product_variant_id">
											<td>{{ variantLabel(row.product_variant_id) }}</td>
											<td><input v-model="row.marketplace_variant" class="form-input" type="text" /></td>
											<td><input v-model="row.stock_code" class="form-input" type="text" /></td>
											<td><input v-model="row.barcode" class="form-input" type="text" /></td>
										</tr>
										<tr v-if="!form.variants.length">
											<td colspan="4" class="lst-empty">Varyant yok.</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<aside class="lst-summary">
							<div class="lst-summary-card">
								<img v-if="product.image" :src="product.image" :alt="product.name" class="lst-summary-img" />
								<h3>Ürün Site Özeti</h3>
								<p><b>Adı:</b> {{ product.name }}</p>
								<p><b>Marka:</b> {{ product.brand || '—' }}</p>
								<p><b>Kategori:</b> {{ product.categoryPath || '—' }}</p>
								<p><b>Satış Fiyatı:</b> {{ money(product.price) }}</p>
								<p v-if="product.marketPrice != null"><b>Piyasa Fiyatı:</b> {{ money(product.marketPrice) }}</p>
								<p><b>Satış Durumu:</b> {{ product.status }}</p>
								<a :href="product.editUrl" class="lst-edit-link">Düzenle</a>
							</div>
						</aside>
					</div>
				</div>

				<footer class="lst-foot">
					<button class="btn btn-ghost btn-sm" @click="close">Kapat</button>
					<button class="btn btn-primary btn-sm" :disabled="saving || loading" @click="save">
						{{ saving ? 'Kaydediliyor…' : 'Kaydet' }}
					</button>
				</footer>
			</aside>
		</div>
	</teleport>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'
import CustomSelect from '@/Components/CustomSelect.vue'

const props = defineProps({
	open: { type: Boolean, default: false },
	productId: { type: [Number, String], default: null },
	marketplace: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const showToast = inject('showToast')

const loading = ref(false)
const saving = ref(false)
const form = ref(null)
const product = ref({})
const variantsMeta = ref([])

const statusOptions = [
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]
const currencyOptions = [
	{ value: 'TL', label: 'TL' },
	{ value: 'USD', label: 'USD' },
	{ value: 'EUR', label: 'EUR' },
]

const approvalLabels = {
	not_sent: 'Gönderilmedi',
	pending: 'Onay Bekliyor',
	approved: 'Onaylandı',
	rejected: 'Reddedildi',
}
const approvalLabel = computed(() => approvalLabels[form.value?.approval_status] ?? '—')

function money(v) {
	return '₺' + Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function variantLabel(id) {
	return variantsMeta.value.find((v) => v.id === id)?.label ?? '—'
}

function close() {
	emit('close')
}

async function load() {
	if (!props.productId || !props.marketplace) return
	loading.value = true
	form.value = null
	try {
		const { data } = await axios.get(`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`)
		product.value = data.product
		variantsMeta.value = data.variants
		form.value = data.listing
	} catch (e) {
		showToast?.({ type: 'error', title: 'Listeleme yüklenemedi', message: e?.response?.statusText || 'Hata' })
		close()
	} finally {
		loading.value = false
	}
}

async function save() {
	if (!form.value) return
	saving.value = true
	try {
		const { data } = await axios.put(
			`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`,
			form.value,
		)
		showToast?.({ type: 'success', title: 'Kaydedildi', message: `${props.marketplace.name} listelemesi güncellendi.` })
		emit('saved', { marketplaceKey: props.marketplace.key, ...data.listing })
		close()
	} catch (e) {
		const msg = e?.response?.data?.message || Object.values(e?.response?.data?.errors ?? {})[0]?.[0] || 'Sunucu hatası.'
		showToast?.({ type: 'error', title: 'Kaydedilemedi', message: msg })
	} finally {
		saving.value = false
	}
}

watch(() => props.open, (v) => { if (v) load() })
</script>

<style scoped>
.lst-overlay { position: fixed; inset: 0; background: rgba(15, 15, 30, 0.45); z-index: 1000; display: flex; justify-content: flex-end; }
.lst-drawer { width: min(960px, 96vw); height: 100%; background: #f7f7fb; display: flex; flex-direction: column; box-shadow: -8px 0 30px rgba(0,0,0,.18); }
.lst-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: #fff; border-bottom: 1px solid #ebebf0; }
.lst-title { display: flex; align-items: center; gap: 10px; }
.lst-title h2 { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.lst-mp-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px; color: #fff; font-size: 11px; font-weight: 800; }
.lst-close { background: none; border: none; font-size: 18px; color: #888; cursor: pointer; }
.lst-loading { padding: 40px; text-align: center; color: #888; }
.lst-body { flex: 1; overflow-y: auto; padding: 18px 20px; }
.lst-alert { padding: 12px 16px; border-radius: 10px; font-weight: 600; font-size: 13px; margin-bottom: 16px; }
.lst-alert.warn { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.lst-alert.ok { background: #ecfdf3; color: #16a34a; border: 1px solid #bbf7d0; }
.lst-grid { display: grid; grid-template-columns: 1fr 280px; gap: 18px; align-items: start; }
@media (max-width: 820px) { .lst-grid { grid-template-columns: 1fr; } }
.lst-main { display: flex; flex-direction: column; gap: 14px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12.5px; font-weight: 600; color: #555; }
.form-input { height: 36px; border: 1.5px solid #e8e8f0; border-radius: 9px; background: #fff; padding: 0 12px; font-family: inherit; font-size: 13px; color: #1a1a2e; outline: none; width: 100%; }
.form-input:focus { border-color: rgb(var(--color-primary)); box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.1); }
.lst-readonly { height: 36px; display: flex; align-items: center; padding: 0 12px; background: #f0f0f5; border-radius: 9px; font-size: 13px; color: #666; }
.lst-price-row { display: grid; grid-template-columns: 1fr 90px; gap: 6px; }
.lst-variants { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px; }
.lst-variants-head { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 10px; }
.lst-table { width: 100%; border-collapse: collapse; }
.lst-table th { text-align: left; font-size: 11.5px; color: #888; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #f0f0f5; }
.lst-table td { padding: 6px 8px; }
.lst-table .form-input { height: 32px; }
.lst-empty { text-align: center; color: #aaa; padding: 14px; }
.lst-summary-card { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px; font-size: 12.5px; color: #444; display: flex; flex-direction: column; gap: 4px; }
.lst-summary-card h3 { font-size: 13px; font-weight: 700; color: #1a1a2e; margin: 4px 0; }
.lst-summary-card p { margin: 0; }
.lst-summary-img { width: 100%; height: 160px; object-fit: cover; border-radius: 9px; margin-bottom: 6px; }
.lst-edit-link { color: rgb(var(--color-primary)); font-weight: 600; margin-top: 6px; text-decoration: none; }
.lst-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; background: #fff; border-top: 1px solid #ebebf0; }
</style>
```

- [ ] **Step 2: Build ile derleme doğrula**

Run: `npx vite build 2>&1 | grep -E "built in|error|Error"`
Expected: `✓ built in ...` (hata yok)

- [ ] **Step 3: Commit**

```bash
git add Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue
git commit -m "feat(product): pazaryeri listeleme drawer bileseni"
```

---

## Task 6: `Products.vue` — logo tıklama + drawer + özet güncelleme

**Files:**
- Modify: `Modules/Product/Resources/assets/js/Pages/Products.vue`

- [ ] **Step 1: `mp-item`'ı tıklanabilir yap**

`Products.vue` template'inde `mp-item` div'ini güncelle (mevcut `<div ... class="mp-item" :title="mp.name">` açılışını değiştir):

```html
								<button
									v-for="mp in marketplacesFor(p)"
									:key="mp.key"
									type="button"
									class="mp-item"
									:class="{ sent: listingSummary(p, mp)?.isSent }"
									:title="mp.name"
									@click="openListing(p, mp)"
								>
```

ve bu döngünün kapanışını `</div>` yerine `</button>` yap.

- [ ] **Step 2: Logo altı fiyatı gerçek listelemeden göster**

Aynı blokta `<span class="mp-price">{{ platformPrice(p, mp) }}</span>` satırını değiştir:

```html
									<span class="mp-price">{{ mpPrice(p, mp) }}</span>
```

- [ ] **Step 3: Drawer bileşenini template'e ekle**

`Products.vue` template'inin en sonuna, kök `</div>` kapanışından **önce** ekle:

```html
		<MarketplaceListingDrawer
			:open="listingOpen"
			:product-id="activeProduct?.id"
			:marketplace="activeMarketplace"
			@close="listingOpen = false"
			@saved="onListingSaved"
		/>
```

- [ ] **Step 4: Script'e import + state + fonksiyonlar ekle**

`Products.vue` script'inde:

1. Mevcut `import { ref, computed, watch, inject } from 'vue'` satırını şununla değiştir (reactive eklendi):
   ```js
   import { ref, computed, watch, inject, reactive } from 'vue'
   ```
2. `import CustomSelect from '@/Components/CustomSelect.vue'` satırının altına ekle (modül bileşeni göreli yolda; `@` = `resources/js` olduğundan göreli import kullan):
   ```js
   import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'
   ```

`marketplacesFor` fonksiyonundan sonra ekle:

```js
/* ── Pazaryeri listeleme drawer ── */
const listingOpen = ref(false)
const activeProduct = ref(null)
const activeMarketplace = ref(null)
const listingOverlay = reactive({}) // `${productId}:${key}` -> { price, isSent }

function listingSummary(p, mp) {
	return listingOverlay[`${p.id}:${mp.key}`] ?? p.listings?.[mp.key] ?? null
}
function mpPrice(p, mp) {
	const s = listingSummary(p, mp)
	return s && s.price != null ? formatPrice(s.price) : platformPrice(p, mp)
}
function openListing(p, mp) {
	activeProduct.value = p
	activeMarketplace.value = mp
	listingOpen.value = true
}
function onListingSaved(payload) {
	if (!activeProduct.value) return
	listingOverlay[`${activeProduct.value.id}:${payload.marketplaceKey}`] = {
		price: payload.price,
		isSent: payload.isSent,
	}
}
```

- [ ] **Step 5: `mp-item`'a buton stilini ekle (soluk/gönderilmiş)**

`Products.vue` `<style>` içinde `.mp-item { ... }` kuralını güncelle ve altına ekle:

```css
.mp-item { display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 0 0 auto; background: none; border: none; cursor: pointer; padding: 2px; opacity: .55; transition: opacity .12s; }
.mp-item:hover { opacity: 1; }
.mp-item.sent { opacity: 1; }
```

- [ ] **Step 6: Build ile derleme doğrula**

Run: `npx vite build 2>&1 | grep -E "Products-|built in|error|Error"`
Expected: `Products-*.js` derlenir, `✓ built in ...` (hata yok)

- [ ] **Step 7: Commit**

```bash
git add Modules/Product/Resources/assets/js/Pages/Products.vue
git commit -m "feat(product): katalogda logo tiklayinca listeleme drawer"
```

---

## Task 7: Uçtan uca doğrulama

**Files:** (yok — yalnızca doğrulama)

- [ ] **Step 1: Tüm backend testleri çalıştır**

Run: `php artisan test --filter=MarketplaceListingTest`
Expected: 4 test PASS.

- [ ] **Step 2: Tam build**

Run: `npx vite build 2>&1 | grep -E "built in|error|Error"`
Expected: `✓ built in ...` (hata yok)

- [ ] **Step 3: Manuel doğrulama (kullanıcı/QA)**

`! npm run dev` ile dev sunucusu, sonra `/products`:
1. Bir pazaryeri logosuna tıkla → drawer açılır, ürün özeti + varsayılan taslak gelir.
2. Alanları doldur + Kaydet → toast, drawer kapanır, logo belirginleşir (sent), logo altı fiyat girilen değeri gösterir.
3. Aynı logoya tekrar tıkla → kaydedilen değerler geri gelir.
4. Farklı pazaryeri → ayrı kayıt.

- [ ] **Step 4: Plan tamam — final commit (gerekiyorsa)**

```bash
git status
```

---

## Self-Review Notları
- **Spec kapsamı:** Tablo+model (Task 1), show/upsert+rotalar+yetki (Task 2-3), index özeti (Task 4), drawer (Task 5), Products.vue entegrasyon + soluk/sent logo + gerçek fiyat (Task 6), doğrulama (Task 7). Tüm spec maddeleri karşılanıyor.
- **Tip tutarlılığı:** `show` → `{ marketplace, product, variants, listing }`; `listing.variants[*]` = `{ product_variant_id, marketplace_variant, stock_code, barcode }`. Drawer aynı şekli `form`'a alır, `save` aynısını PUT eder. `upsert` dönüşü `{ ok, listing:{ price, isSent } }`; `onListingSaved` `payload.price`/`payload.isSent` kullanır — tutarlı.
- **Yetki:** `show` herkese (katalog erişimi), `upsert` `can:product.add`. Test `assertForbidden` ile doğrular (rolsüz kullanıcı).
- **DB disiplini:** Migration `down()` gerçek ters işlem (`dropIfExists`). Varyantlar JSON — ekstra tablo yok.
