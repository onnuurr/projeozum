# Marketplace Modül İzolasyonu — Tasarım

**Tarih:** 2026-07-11
**Durum:** Onaylandı, uygulama planı bekleniyor

## Bağlam

`8d8d2d3` commit'i pazaryeri (Trendyol/Hepsiburada/N11/Çiçeksepeti) entegrasyonunun dosyalarını
Tenant/Product modüllerinden çıkarıp `Modules/Marketplace` altında topladı. Ancak dosya taşıma
tek başına modül sınırını kapatmadı: Product ve Tenant modülleri hâlâ Marketplace'in somut
model/controller/Vue bileşenlerine doğrudan bağımlı. Bu tasarım, kalan bağımlılıkları kapatıp
**tek yönlü bağımlılık** (Marketplace → Product, Marketplace → Tenant) elde etmeyi hedefler.

## Mevcut durum denetimi (2026-07-11 tarihli kod taraması)

Normal/kaçınılmaz kabul edilenler (dokunulmuyor):
- Migration FK'ları (`tenant_id`, `product_id`, `category_id` → Marketplace tabloları)
- `Tenant::marketplaceCredentials()` ilişkisi — `TenantMarketplaceController` (admin, tenant kimlik
  bilgisi yönetimi) zaten Marketplace'in kendi özelliği, bu tasarımın kapsamı dışında

Kapatılacak sızıntılar:
1. `Modules/Product/routes/web.php` içinde `Modules\Marketplace\Http\Controllers\ProductMarketplaceListingController`'a doğrudan route tanımı
2. `Modules/Product/Http/Controllers/CategoryController.php` — `connectMarketplace`, `storeMapping`,
   `destroyMapping` metodları + `Marketplace`/`CategoryMarketplaceMapping` modellerini doğrudan sorguluyor
3. `Modules/Product/Http/Controllers/ProductController.php` + `ProductCatalogPresenter` — katalog
   satırlarında pazaryeri rozetleri için `Marketplace` modelini ve `listings`/`marketplaceMappings`
   ilişkilerini doğrudan kullanıyor
4. `Modules/Tenant/Services/TenantFinancialsService.php`, `ProfitCalculatorService.php` —
   `marketplace_sales`/`marketplace_expenses` tablolarını ve `MarketplaceCommissionRate` modelini
   doğrudan sorguluyor
5. `Modules/Product/Resources/assets/js/Pages/Products.vue`, `Categories.vue` — Marketplace'in
   Vue bileşenlerini (`MarketplaceListingDrawer`, `MarketplaceConnectDrawer`,
   `MarketplaceCategoryPickerModal`) doğrudan import ediyor

Ek bulgu: `Modules\Marketplace\Services\AbstractMarketplaceService::lookupRates()` ile
`Modules\Tenant\Services\ProfitCalculatorService::lookupRates()` birebir aynı sorguyu
tekrarlıyor — bu tasarım kapsamında tek kaynağa indirgenir.

## Karar özeti

| Konu | Karar |
|---|---|
| Ürün/kategori düzenleme sayfalarındaki pazaryeri drawer/modal'ı | Marketplace'in kendi ayrı admin sayfalarına taşınır (UX değişir) |
| Products.vue katalog listesindeki salt-okunur pazaryeri rozetleri | Tamamen kaldırılır (Product, Marketplace'i hiç bilmez) |
| Tenant finans hesaplaması (satış/gider/komisyon) | Marketplace'in yayınladığı dar bir contract üzerinden (Tenant → Marketplace, arayüz seviyesinde) |
| Taşınan admin ekranlarının yetkisi | Yeni izole izin: `marketplace.catalog.manage` (sadece superadmin — `marketplace.manage` tenant rolüne de atanmış olduğundan karışıklığı önlemek için) |
| Yeni sayfaların navigasyonu | Kod/route/sayfa oluşturulur; superadmin menüsüne ekleme kullanıcıya bırakılır |

## Mimari

```
Product ──┐                    Tenant ──┐
          │ (bağımlılık yok)            │ (yalnız MarketplaceFinancialsContract arayüzü)
          ▼                              ▼
                    Marketplace
          ▲                              ▲
          │ (salt-okunur, izinli)        │ (salt-okunur, izinli)
       Product                        Tenant
```

Marketplace, Product ve Tenant'ın modellerini okumaya devam eder (entegrasyonun doğası gereği
kaçınılmaz). Product ve Tenant asla Marketplace'in somut sınıflarını import etmez — Tenant'ın tek
istisnası, aşağıda tanımlanan dar contract'tır.

## Backend değişiklikleri

### Yeni: `CategoryMappingController` (Modules/Marketplace/Http/Controllers)
`Product\CategoryController`'daki `connectMarketplace`, `storeMapping`, `destroyMapping`
metodları + kategori/mapping listesi hazırlama mantığı buraya taşınır. Kategori listesini
`Modules\Product\Models\Category`'den salt-okunur okur.

### `ProductMarketplaceListingController` (zaten Marketplace'te)
Değişmez — sadece route grubu ve yetkisi değişir, artı yeni bir "ürün seç" giriş sayfası kazanır.

### Yeni: `Modules\Marketplace\Contracts\MarketplaceFinancialsContract`

```php
interface MarketplaceFinancialsContract
{
    public function salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;
    public function expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;
    public function salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array;
    public function salesByMonth(int $tenantId, int $months): array;
    public function commissionAndShippingRates(string $marketplace, int $categoryId): array;
}
```

`Modules\Marketplace\Services\MarketplaceFinancialsService` bunu implemente eder; driver-aware
(pgsql/mysql/sqlite) ay gruplama sorgusu ve `commissionAndShippingRates` (mevcut `lookupRates`
mantığı, tek kaynağa indirgenmiş hali) buraya taşınır. `AbstractMarketplaceService` de artık bu
implementasyonu kullanır (kod tekrarı giderilir). `MarketplaceServiceProvider::register()`'da
singleton bind edilir.

`TenantFinancialsService` ve `ProfitCalculatorService` constructor'dan `MarketplaceFinancialsContract`
alır; `DB::table('marketplace_*')` ve `MarketplaceCommissionRate` doğrudan kullanımları kalkar.

### Yeni izin: `marketplace.catalog.manage`
`MarketplacePermissionSeeder`'a eklenir, yalnız `superadmin` rolüne atanır (mevcut `category.manage`
ile aynı kapsam — tenant rolüne verilmez).

### Route taşınması
`Modules/Product/routes/web.php`'den şu route'lar silinir, `Modules/Marketplace/routes/web.php`'e
`can:marketplace.catalog.manage` ile eklenir:
- `products.categories.marketplaces.store/destroy` → `marketplace.categories.mappings.store/destroy`
- `products.marketplaces.connect` → `marketplace.categories.connect`
- `products.listings.show/upsert` → `marketplace.products.listings.show/upsert`

### Product/Tenant'tan silinecekler
- `Product::listings()`, `Category::marketplaceMappings()` ilişki metodları + Marketplace importları
- `ProductController@index`'teki `marketplaces` prop'u + `category.marketplaceMappings`/
  `listings.marketplace` eager-load'ları + `use Modules\Marketplace\Models\Marketplace`
- `CategoryController`'daki 3 marketplace metodu + `marketplaces` prop'u + importları
- `ProductCatalogPresenter::catalogRow()`'daki `marketplaces`/`listings` alanları
- `TenantFinancialsService`/`ProfitCalculatorService`'teki doğrudan Marketplace tablo/model erişimi

## Frontend değişiklikleri

### Product'tan kaldırılacaklar
- **Products.vue**: pazaryeri rozet bloğu, `MarketplaceListingDrawer` import + kullanımı,
  `marketplaces` prop'u, `marketplacesFor()`, drawer state'i (`activeMarketplace`, `listingOverlay`)
- **Categories.vue**: pazaryeri matris UI'sinin tamamı (stat satırı, tablo sütunları, connect/
  mapping drawer+modal, ilgili state ve fonksiyonlar) — kaldırınca sade bir kategori CRUD sayfası
  kalır

### Marketplace'e eklenecek yeni admin sayfaları
1. **`CategoryMapping.vue`** — Categories.vue'den taşınan matris UI'nin birebir karşılığı; mevcut
   `MarketplaceConnectDrawer`, `MarketplaceCategoryPickerModal` bileşenleri (zaten Marketplace
   Resources altında) burada kullanılır.
2. **`ProductListings.vue`** — ürün arama/otomatik tamamlama (Marketplace'in Product'ı salt-okunur
   sorgulaması) + seçilen ürün için mevcut `MarketplaceListingDrawer` açılır.

Navigasyon: route + sayfa oluşturulur; superadmin menüsüne ekleme bu iş kapsamında yapılmaz.

## Test planı

- **Yeni:** `tests/Feature/Marketplace/CategoryMappingTest.php` — connect/mapping/unmapping akışı +
  `marketplace.catalog.manage` yetki kontrolü
- **Taşınan:** `tests/Feature/Product/MarketplaceListingTest.php` →
  `tests/Feature/Marketplace/ProductListingTest.php`, URL'ler yeni route'lara güncellenir
- **Dokunulmaması beklenen:** `TenantFinancialsServiceTest`, `ProfitCalculatorTest`,
  `CommissionLookupTest` — `app(...)` container-resolve kullandıklarından contract binding'i
  doğru yapılırsa değişiklik gerekmeden geçmeli (doğrulanacak)
- Değişiklik sonrası `php artisan test --filter=Product`, `--filter=Marketplace`,
  `--filter=Tenant` çalıştırılır

## Kapsam dışı

- `Tenant::marketplaceCredentials()` ilişkisi ve `TenantMarketplaceController` (zaten
  Marketplace'in kendi özelliği, bu iş öncesinde de doğru sınırdaydı)
- Superadmin menüsüne yeni sayfaların eklenmesi (kullanıcı isteğiyle kapsam dışı bırakıldı)
- Portal tarafı (tenant-facing) Marketplace sayfaları (`Portal/Marketplace/*`) — zaten izole,
  bu tasarımın konusu değil
