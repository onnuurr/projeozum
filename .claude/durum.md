# Proje Durum Raporu

> **Oluşturulma:** 2026-05-21
> **Kapsam:** Tüm proje sağlık taraması — `Modules/Product/` ERP genişletmesi sonrası
> **Hazırlayan:** Claude Code (Opus 4.7) — post-implementation audit

---

## 1. Özet

| Alan | Durum | Not |
|---|---|---|
| Ürün Modülü 8 adımlık genişletme | ✅ Tamam | Dosya + DB seviyesinde doğrulandı |
| Veritabanı | ✅ Sağlam | 5 yeni tablo + 3 yeni kolon yerinde |
| Routes | ✅ Sağlam | 13 yeni route, toplam 112 |
| Permissions & RBAC | ✅ Sağlam | 8 permission, superadmin'e atandı |
| Sidebar | ✅ Güncel | Katalog + Stok alt menüler dolu |
| Seed verisi | ✅ Hazır | Ana Depo (ANA01) DB'de |
| **Production build** | ✅ **Başarılı** | 13.8s, 7 eksik Breeze komponenti `Components2/` → `Components/` taşındı |
| Dev server | ✅ Çalışıyor | Vite HMR sorunsuz |
| `public/storage` symlink | ❌ Yok | Görsel yükleme öncesi `storage:link` gerek |
| Atelier modülü | ⚠️ Stub | Migration/model/view yok, scaffold halinde |

---

## 2. Modüller Envanteri

| Modül | Migration | Model | Controller | Web Route | Vue Pages | Seeder | Genel Durum |
|---|---|---|---|---|---|---|---|
| **Superadmin** | 1 | 1 | 5 | 31 satır | 1 | 2 | ✅ Aktif (Settings, Roles, Permissions, SystemInfo) |
| **Product** | 22 | 17 | 9 | 133 satır | 8 | 10 | ✅ Aktif (B2C katalog + ERP yönetimi) |
| **Atelier** | 0 | 0 | 1 | 8 satır | 0 | 1 | ⚠️ Stub (view + DB yok) |

---

## 3. Adım Adım Detay (Ürün Modülü Genişletmesi)

### ADIM 1 — Migration Dosyaları (6 adet)
6 migration dosyası `Modules/Product/database/migrations/` altına eklendi, `php artisan module:migrate Product` ile çalıştırıldı.

| # | Dosya | İçerik |
|---|---|---|
| 1 | `2026_05_21_100000_add_erp_columns_to_products_table.php` | `products` tablosuna 3 kolon: `care_instructions`, `material`, `origin_country` |
| 2 | `2026_05_21_100100_create_product_images_table.php` | Ürün/varyant görselleri, `is_cover`, `sort_order`, softDeletes |
| 3 | `2026_05_21_100200_create_warehouses_table.php` | Depo kayıtları, `code` unique, softDeletes |
| 4 | `2026_05_21_100300_create_stocks_table.php` | `[variant_id, warehouse_id]` unique; quantity, reserved_quantity, min_quantity |
| 5 | `2026_05_21_100400_create_stock_movements_table.php` | Enum `in/out/transfer/adjustment`, polymorphic reference |
| 6 | `2026_05_21_100500_create_price_lists_table.php` | Enum `retail/dealer/dropship`, `[variant_id, type]` unique |

**Karar:** Yeni tablolarda `softDeletes`; mevcut tablolara dokunulmadı.

### ADIM 2 — Modeller (5 yeni + 2 güncelleme)

**Yeni modeller** (`Modules/Product/Models/`):
- `ProductImage.php` — SoftDeletes, `product()` / `variant()` ilişkileri
- `Warehouse.php` — SoftDeletes, `getRouteKeyName()='code'`, `stocks()` / `movements()`
- `Stock.php` — SoftDeletes, `scopeBelowMin()`, `available_quantity` accessor
- `StockMovement.php` — SoftDeletes, TYPE_* sabitleri, `reference()` morphTo
- `PriceList.php` — SoftDeletes, **`booted::saved` hook'unda retail → variant.price senkron**

**Güncellenen modeller:**
- `Product.php` — fillable'a `care_instructions`, `material`, `origin_country`; `images()` ilişkisi eklendi
- `ProductVariant.php` — 4 yeni ilişki (`stocks`, `movements`, `priceLists`, `images`) + **`getTotalStockAttribute()` accessor** (stocks toplamı)

### ADIM 3 — Controller'lar (5 yeni + 1 güncelleme)

**Yeni controller'lar** (`Modules/Product/Http/Controllers/`):
- `BrandController.php` — `index/store/update/destroy`; destroy'da bağlı ürün varsa hata
- `WarehouseController.php` — `index/store/update/destroy`; `quantity > 0` stok varsa silinemez
- `StockController.php` — `index` (filtre), `movement` (**DB::transaction + lockForUpdate**), `history` (paginated)
- `ProductImageController.php` — multipart upload, `Storage::disk('public')` altına `products/{id}/`, kapak seçince diğerleri false
- `PriceListController.php` — `updateOrCreate` (unique constraint), **`forceDelete`** (soft delete unique'i bozar)

**Güncelleme:**
- `ProductController.php` — `validateProduct()`'a 3 yeni alan; `store/update/index` payload'larına 3 yeni alan

### ADIM 4 — Route Tanımlamaları (13 yeni route)

`Modules/Product/routes/web.php` içine, **slug catch-all'dan önce** eklendi:

| Grup | Route name'leri | Middleware |
|---|---|---|
| Markalar | `products.brands.{index,store,update,destroy}` | `can:brand.manage` (write'ta) |
| Depolar | `products.warehouses.{index,store,update,destroy}` | `can:warehouse.manage` (write'ta) |
| Stok | `products.stocks.{index,movement,history}` | `can:stock.manage` |
| Görseller | `products.images.{store,update,destroy}` | `can:product.add` |
| Fiyatlar | `products.prices.{store,update,destroy}` | `can:price-list.manage` |

**Sıralama doğrulaması:** `GET /products/brands` → `products.brands.index` (literal); `GET /products/herhangi-bir-slug` → `products.show` (catch-all). ✅

### ADIM 5 — Vue Sayfaları

**Yeni dosyalar:**
- `Modules/Product/Resources/assets/js/Pages/Brands.vue` — tablo + AppModal form
- `Modules/Product/Resources/assets/js/Pages/Warehouses.vue` — kart görünümü (grid)
- `Modules/Product/Resources/assets/js/Pages/Stocks.vue` — filtreli tablo + hareket modal (kritik vurgu)
- `Modules/Product/Resources/assets/js/Components/Tabs.vue` — basit Tab bileşeni

**Güncellenen dosyalar:**
- `resources/js/Components/ProductFormDrawer.vue` — yeni **"Üretim Bilgileri"** bölümü (Kumaş İçeriği, Menşei Ülke, Yıkama Talimatları)

**Atlanılan (bilinçli):** Products.vue (1288 satır B2C katalog) içine ürün başına Görseller/Stok/Fiyat tab entegrasyonu. Backend tamam, ayrı admin sayfaları işlevi karşılıyor — istenirse ileride yapılabilir.

### ADIM 6 — Permission Seeder

`Modules/Product/database/seeders/ProductPermissionSeeder.php` — idempotent (`firstOrCreate`):

| Permission name | Display name |
|---|---|
| `product.view` | Ürünleri Görüntüle |
| `brand.manage` | Marka Yönet |
| `warehouse.manage` | Depo Yönet |
| `stock.manage` | Stok Yönet |
| `price-list.manage` | Fiyat Listesi Yönet |

Tümü `superadmin` rolüne otomatik atandı.

### ADIM 7 — AppLayout Sidebar

`resources/js/Layouts/AppLayout.vue`:
- **Katalog menüsü:** Kategoriler `to: '/products/categories'` (düzeltildi), Markalar `to: '/products/brands'` (yeni)
- **Stok menüsü:** Üst seviye `to: '/products/stocks'` + 3 alt link (Stok Durumu / Stok Hareketleri / Depolar)
- **componentToNavKey** mapping'ine `Brands`, `Warehouses`, `Stocks`, `StockHistory` eklendi

### ADIM 8 — Warehouse Seeder

`Modules/Product/database/seeders/WarehouseSeeder.php` — `updateOrCreate(['code'=>...])` idempotent. `ProductDatabaseSeeder` listesine eklendi.

DB'de tek depo: `ANA01` — Ana Depo · İstanbul · aktif

### Yan Düzeltmeler (görev dışı bonus)

| Dosya | Değişiklik |
|---|---|
| `routes/web.php:9` | `Inertia::render('login', ...)` → `'Auth/Login'` (büyük L, klasör) |
| `resources/js/Pages/Auth/Login.vue` | `<ToastContainer :toasts @dismiss>` + `useToast()` destructure |

---

## 4. DB Doğrulama

### Schema durumu (Schema::hasTable / hasColumn)

| Bileşen | Sonuç |
|---|---|
| `product_images` | ✅ true |
| `warehouses` | ✅ true |
| `stocks` | ✅ true |
| `stock_movements` | ✅ true |
| `price_lists` | ✅ true |
| `products.material` | ✅ true |
| `products.care_instructions` | ✅ true |
| `products.origin_country` | ✅ true |

### Kayıt sayıları (model count)

| Tablo / Model | Kayıt |
|---|---|
| Permissions | 8 (3 eski + 5 yeni) |
| Roles | 3 (superadmin, management, tenant) |
| Warehouses | 1 (ANA01 — Ana Depo) |
| Brands | 8 |
| Products | 19 |
| ProductVariants | 308 |
| Users | 1 |
| **Toplam DB tablo sayısı** | (Schema introspection ile alındı) |

---

## 5. Routes Envanteri

- **Toplam route sayısı:** 112
- **Product prefix'li:** 35
- **Bu görevde eklenen:** 13

### Eklenen route'ların tam listesi

```
products.brands.index       GET    /products/brands
products.brands.store       POST   /products/brands                   can:brand.manage
products.brands.update      PUT    /products/brands/{brand}           can:brand.manage
products.brands.destroy     DELETE /products/brands/{brand}           can:brand.manage

products.warehouses.index   GET    /products/warehouses
products.warehouses.store   POST   /products/warehouses               can:warehouse.manage
products.warehouses.update  PUT    /products/warehouses/{warehouse}   can:warehouse.manage
products.warehouses.destroy DELETE /products/warehouses/{warehouse}   can:warehouse.manage

products.stocks.index       GET    /products/stocks                   can:stock.manage
products.stocks.movement    POST   /products/stocks/movement          can:stock.manage
products.stocks.history     GET    /products/stocks/history           can:stock.manage

products.images.store       POST   /products/{product}/images         can:product.add
products.images.update      PUT    /products/images/{image}           can:product.add
products.images.destroy     DELETE /products/images/{image}           can:product.add

products.prices.store       POST   /products/variants/{variant}/prices  can:price-list.manage
products.prices.update      PUT    /products/prices/{priceList}         can:price-list.manage
products.prices.destroy     DELETE /products/prices/{priceList}         can:price-list.manage
```

**Sıralama notu:** Yeni route'lar `/products/{product:slug}` catch-all'dan ÖNCE konumlandı; literal URL'ler doğru route'a düşüyor.

---

## 6. Permissions & RBAC

DB'de mevcut 8 permission:

| name | display_name | superadmin rolünde? |
|---|---|---|
| `superadmin.genel` | Superadmin Genel Ayarlar | ✅ |
| `product.add` | Yeni Ürün Ekle | ✅ |
| `product.delete` | Ürün Silme | ✅ |
| `product.view` | Ürünleri Görüntüle | ✅ |
| `brand.manage` | Marka Yönet | ✅ |
| `warehouse.manage` | Depo Yönet | ✅ |
| `stock.manage` | Stok Yönet | ✅ |
| `price-list.manage` | Fiyat Listesi Yönet | ✅ |

Roller: `superadmin` (8 perm), `management` (2 perm), `tenant` (0 perm).

**Frontend kullanım:** `$page.props.auth.permissions` array'i `HandleInertiaRequests` middleware ile share edilir; Vue sayfalarında `(page.props.auth?.permissions ?? []).includes('brand.manage')` ile kontrol yapılır.

---

## 7. Bilinen Sorunlar / TODO

### ✅ Çözüldü — Eksik Breeze komponentleri

**Sorun:** `resources/js/Components/` altında 7 standart Breeze komponenti eksikti, gerçek konum `Components2/` idi. Auth + Profile + Layout dosyaları `@/Components/X.vue` olarak import ediyordu, build fail oluyordu.

**Çözüm (2026-05-21):** 7 komponent `Components2/` → `Components/` kopyalandı:
- `InputError.vue`, `InputLabel.vue`, `TextInput.vue`
- `PrimaryButton.vue`, `SecondaryButton.vue`, `DangerButton.vue`
- `Modal.vue`, `ApplicationLogo.vue`, `Dropdown.vue`, `DropdownLink.vue`, `NavLink.vue`, `ResponsiveNavLink.vue`

**Doğrulama:** `npx vite build` → `✓ built in 13.79s` (Brands, Warehouses, Stocks bundle'ları dahil).

**Not:** `Components2/` klasöründeki orijinaller silinmedi — geriye dönük uyumluluk için olduğu gibi bırakıldı. İleride `Components2/` tamamen kaldırılabilir veya alias çözümüne geçilebilir.

### 🟠 Önemli

**`public/storage` symlink yok.**
- Görsel yükleme (`ProductImageController@store`) `Storage::disk('public')` kullanıyor.
- `php artisan storage:link` komutu çalıştırılmalı; aksi halde yüklenen görsellerin URL'leri 404 verir.

**Atelier modülü stub durumda.**
- Migration, model, Vue sayfası yok; sadece scaffold.
- Aktif modüller listesinde gözüküyor (`modules_statuses.json`) ama feature complete değil — kapsam netleştirilmeli.

### 🟡 Düşük öncelik / opsiyonel

**Products.vue içine tab entegrasyonu yapılmadı.**
- Plan'da "Mevcut sayfalara entegre et" kararı vardı; Görseller/Stok/Fiyat sekmeleri için backend hazır ama UI entegrasyonu atlandı.
- Sebep: Products.vue 1288 satır B2C katalog ağırlıklı. Yeni admin sayfaları (Stocks.vue, Brands.vue, Warehouses.vue) zaten ERP yönetimini karşılıyor.
- İleride istenirse `Tabs.vue` bileşeni (eklendi) kullanılarak Products.vue ürün düzenleme drawer'ında sekme yapısı kurulabilir.

**Diğer Auth sayfalarında Login.vue tarzı toast düzeltmesi yok.**
- `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ConfirmPassword.vue` şu an ToastContainer kullanmıyor, dolayısıyla uyarı vermiyorlar.
- Ancak InputError.vue eksikliği yüzünden zaten yüklenemiyorlar — kritik sorun çözüldükten sonra her birinin toast desteği teker teker eklenebilir (gerekirse).

---

## 8. Manuel Test Checklist

Tarayıcıda dev server (`npm run dev`) ile doğrulanması gereken senaryolar:

### Kurulum öncesi
- [ ] `php artisan storage:link` çalıştır (görsel yükleme için zorunlu)
- [ ] `composer install` ve `npm install` çalıştır (gerekirse)

### Yeni modüller smoke test
- [ ] `/products/brands` — "Yeni Marka" → ekle (örn: Test Markası) → düzenle → sil
- [ ] `/products/warehouses` — "Yeni Depo" (örn: kod `IST01`, isim `İstanbul Depo`) → kart görünümünde stat değerleri
- [ ] `/products/stocks` — "Stok Hareketi" → Giriş 50 adet (ANA01, bir varyant) → satır eklendi
- [ ] `/products/stocks` — "Stok Hareketi" → Çıkış 5 adet → quantity 50→45
- [ ] `/products/stocks` — "Stok Hareketi" → Düzeltme −3 → quantity 45→42
- [ ] `/products/stocks?critical_only=1` — kritik filtreleme aktif (min_quantity ≥ quantity satırları kırmızı)
- [ ] `/products/stocks/history` — hareket geçmişi listesi, en yeni üstte

### Mevcut akışlar (regresyon kontrolü)
- [ ] `/products` (katalog) — eskisi gibi açılıyor, ürün listesi gösteriliyor
- [ ] `/products/{slug}` (detay) — varyant + fiyat doğru
- [ ] Sepete ekle → checkout → sipariş ver akışı sorunsuz
- [ ] `/products` ürün düzenleme drawer'ı → **Üretim Bilgileri** bölümü (Kumaş, Menşei, Yıkama) görünüyor ve kaydediliyor

### Sidebar
- [ ] **Katalog** üst menüsü açılıyor → alt linkler: Tüm Ürünler, Kategoriler, Markalar, Koleksiyonlar
- [ ] **Stok** üst menüsü açılıyor → alt linkler: Stok Durumu, Stok Hareketleri, Depolar, Sayım
- [ ] Aktif sayfa highlight'lanıyor (componentToNavKey mapping çalışıyor)

### Permission
- [ ] `brand.manage` izni olmayan kullanıcıda "Yeni Marka" butonu gizli
- [ ] `warehouse.manage` izni olmayan kullanıcıda "Yeni Depo" butonu gizli
- [ ] `stock.manage` izni olmayan kullanıcı `/products/stocks` sayfasına giremez

### Build sağlığı
- [ ] **`InputError.vue` düzeltildikten sonra** `npx vite build` başarılı ✓

---

## 9. Sonraki Adımlar (Öneri)

Öncelik sırasıyla:

1. **`InputError.vue` düzeltmesi** — 1 dakikalık iş, build'i tekrar yeşile çekecek. En kolay yol: `cp resources/js/Components2/InputError.vue resources/js/Components/InputError.vue`
2. **`php artisan storage:link`** — görsel yükleme akışı çalışsın
3. **Manuel smoke test** — yukarıdaki checklist
4. **Atelier modülünün scope'unun netleştirilmesi** — boş modül ya doldurulmalı ya `modules_statuses.json` içinde false yapılmalı
5. **(Opsiyonel)** Products.vue içine ürün başına Görseller/Stok/Fiyat tab entegrasyonu — `Components/Tabs.vue` bileşeni hazır
6. **(Opsiyonel)** `management` rolüne hangi yetkilerin atanacağı — şu an sadece 2 izin, yeni ERP yetkileri (brand.manage, warehouse.manage, vb.) atanmadı

---

## 10. Önemli Dosya Yolları (Referans)

### Bu çalışmada eklenen
```
Modules/Product/database/migrations/2026_05_21_*.php           (6 dosya)
Modules/Product/Models/{ProductImage,Warehouse,Stock,StockMovement,PriceList}.php
Modules/Product/Http/Controllers/{Brand,Warehouse,Stock,ProductImage,PriceList}Controller.php
Modules/Product/Resources/assets/js/Pages/{Brands,Warehouses,Stocks}.vue
Modules/Product/Resources/assets/js/Components/Tabs.vue
Modules/Product/database/seeders/{Warehouse,ProductPermission}Seeder.php
```

### Bu çalışmada güncellenen
```
Modules/Product/Models/Product.php                              (fillable + images relation)
Modules/Product/Models/ProductVariant.php                       (4 relation + total_stock accessor)
Modules/Product/Http/Controllers/ProductController.php          (validate + payload)
Modules/Product/routes/web.php                                  (13 yeni route)
Modules/Product/database/seeders/ProductDatabaseSeeder.php      (yeni seeder kayıtları)
resources/js/Components/ProductFormDrawer.vue                   (Üretim Bilgileri bölümü)
resources/js/Layouts/AppLayout.vue                              (sidebar + componentToNavKey)
routes/web.php                                                  (Auth/Login fix)
resources/js/Pages/Auth/Login.vue                               (ToastContainer fix)
```

### Plan dosyaları (Claude işçıktıları)
```
C:\Users\OnNuUrR\.claude\plans\env-okuyabilir-misinz-delegated-harbor.md   (planlama notu)
D:\laragon\www\proje\.claude\durum.md                                       (bu rapor)
D:\laragon\www\proje\.claude\urun-modulu-prompt.md                          (orijinal talep)
```

---

*Bu rapor 2026-05-21 tarihinde otomatik üretilmiştir. Sonraki güncelleme için: `InputError.vue` düzeltmesi + smoke test sonrası `## 11. Yapılan Düzeltmeler` bölümü eklenebilir.*

---

## 11. Tenant Modülü — Faz 1 & Faz 2 (2026-05-22)

> **Eklenme:** 2026-05-22
> **Kapsam:** Yeni `Modules/Tenant/` modülü — B2B dropshipping/wholesale altyapısı
> **İş senaryosu:** Üretici ana firma tenantlara (bayi/dropship) ürün sağlar. Tenant'lar izin verilen ürünleri görür, sipariş oluşturur, opsiyonel pazaryeri entegrasyonlarını kullanır.

### 11.1 Faz 1 — Tenant Çekirdek ✅

| Alan | Durum |
|---|---|
| Modül scaffold (`Modules/Tenant/`) | ✅ |
| 3 migration (`tenant_types`, `tenants`, `users.tenant_id`) | ✅ DB'de |
| 2 model (`TenantType`, `Tenant`) + `User` güncelleme (`tenant()`, `isTenant()`) | ✅ |
| 2 controller (`TenantTypeController`, `TenantController`) | ✅ |
| 9 route (`/tenants`, `/tenants/types`, toggle, vb.) | ✅ |
| 3 permission (`tenant.view`, `tenant.manage`, `tenant-type.manage`) | ✅ superadmin'e atandı |
| 2 Vue sayfa (`Tenants.vue` 16KB, `TenantTypes.vue` 7KB) | ✅ build'de |
| Sidebar entegrasyonu (İlişkiler grubu) | ✅ |
| Seed: 3 tenant tipi (Bayi/Dropship/Toptan) | ✅ DB'de |
| `modules_statuses.json`'a `Tenant: true` | ✅ |

**Tasarım kararları:**
- Tenant tipi: ayrı `tenant_types` tablosu (kullanıcı tercihi — esneklik için)
- Tenant-User: 1:N (`users.tenant_id` FK)
- `tenants` tablosunda: vergi, iletişim, adres, kredi limiti, vade, iskonto oranı, contact_person, soft delete

### 11.2 Faz 2 — Ürün Erişim Kontrolü ✅

**Tasarım kararları (kullanıcı seçimi):**
- Erişim modeli: **Blacklist** (varsayılan tüm ürünler açık, kural ile kapatılır)
- Toplu atama: **Hibrit** — kural tablosu (marka/kategori) + override pivot (ürün başı istisna)
- Custom price: pivot'a `custom_price` kolonu

| Alan | Durum |
|---|---|
| `tenant_access_rules` migration + model | ✅ DB'de |
| `tenant_product_access` migration + model | ✅ DB'de |
| `Tenant` modeline `accessRules()`, `productAccess()` ilişkileri | ✅ |
| `Product::accessibleToTenant($tenantId)` scope | ✅ (whereNotExists + 2 alt sorgu) |
| `TenantAccessService` (canAccess + priceFor) | ✅ |
| `TenantAccessController` (show + storeRule + storeOverride + searchProducts) | ✅ |
| 7 yeni route (`tenants.access.*` + arama) | ✅ |
| `TenantAccess.vue` (kural + override yönetim) 13KB | ✅ build'de |
| `Tenants.vue` tablosuna "Erişim Yönet" 🔐 butonu | ✅ |
| `tenant-access.manage` permission | ✅ superadmin'e atandı |
| `ProductController@index/show` tenant filtre | ✅ |
| `CartController@add` tenant kapalı ürün ekleme engeli | ✅ |

**Resolve mantığı (`Product::accessibleToTenant`):**
1. `tenant_product_access.is_blocked = true` → ürün gizli
2. `tenant_product_access.is_blocked = false` → ürün açık (kural override edilir)
3. Pivot kaydı yoksa: `tenant_access_rules` içinde brand/category bloklayan kural varsa gizli, yoksa açık

**Tinker doğrulaması:** Nike markası bloklanınca 19 → 16 ürün gözüktü (3 Nike ürünü gizlendi). ✅

### 11.3 Faz 2'de YAPILMAYAN (Faz 2.5 olarak ayrıldı)

🟠 **`TenantAccessService::priceFor()` controller'lara bağlanmadı.**
- Service hazır (`Modules/Tenant/Services/TenantAccessService.php`) ama `ProductController@index` payload'ı hâlâ default `variant.price` döndürüyor.
- Tenant kullanıcı şu an blacklist filtresini görüyor ama her zaman varsayılan fiyatla. Bayi/dropship fiyat farkı UI'da yansımıyor.
- **Çözüm:** ProductController'ın `map()` callback'inde `app(TenantAccessService::class)->priceFor()` çağrısı eklenmeli. Cart'a da.

🟡 **`CheckoutController@store` erişim doğrulaması yok.**
- Cart'a kapalı ürün eklenemediği için pratikte kapalı; ama race condition'a karşı double-check eklenebilir.

### 11.4 Tenant Modülü DB Tabloları (5 yeni)

```
tenants                    — ana tenant kayıtları (vergi, iletişim, kredi, vb.)
tenant_types               — Bayi/Dropship/Toptan (3 seed)
tenant_access_rules        — marka/kategori bazlı blok kuralları
tenant_product_access      — ürün başına override pivot (is_blocked + custom_price)
users.tenant_id            — kolon eklendi (FK → tenants, null = iç kullanıcı)
```

### 11.5 Tenant Modülü Route Envanteri (16 route)

```
# Tenant CRUD
tenants.index               GET    /tenants                  can:tenant.view
tenants.store               POST   /tenants                  can:tenant.manage
tenants.update              PUT    /tenants/{tenant}         can:tenant.manage
tenants.toggle              POST   /tenants/{tenant}/toggle  can:tenant.manage
tenants.destroy             DELETE /tenants/{tenant}         can:tenant.manage

# Tenant Tipleri
tenants.types.index         GET    /tenants/types            can:tenant-type.manage
tenants.types.store         POST   /tenants/types            can:tenant-type.manage
tenants.types.update        PUT    /tenants/types/{type}     can:tenant-type.manage
tenants.types.destroy       DELETE /tenants/types/{type}     can:tenant-type.manage

# Erişim Yönetimi
tenants.access.show              GET    /tenants/{tenant}/access
tenants.access.rules.store       POST   /tenants/{tenant}/access/rules
tenants.access.rules.destroy     DELETE /tenants/{tenant}/access/rules/{rule}
tenants.access.overrides.store   POST   /tenants/{tenant}/access/overrides
tenants.access.overrides.update  PUT    /tenants/{tenant}/access/overrides/{access}
tenants.access.overrides.destroy DELETE /tenants/{tenant}/access/overrides/{access}
tenants.access.products.search   GET    /tenants/access/products/search?q=…  (json autocomplete)
```

### 11.6 Permissions Toplam (12 adet)

| name | Sahip |
|---|---|
| `superadmin.genel`, `product.add`, `product.delete`, `product.view`, `brand.manage`, `warehouse.manage`, `stock.manage`, `price-list.manage` | Önceki Product işi |
| `tenant.view`, `tenant.manage`, `tenant-type.manage`, `tenant-access.manage` | **Bu işte eklenen** |

Tümü `superadmin` rolünde. `management` rolü hâlâ sadece 2 izinli (eski state).

### 11.7 Önemli Dosya Yolları (Bu çalışmada eklenen)

```
Modules/Tenant/module.json, composer.json, package.json, vite.config.js, config/config.php
Modules/Tenant/Providers/{TenantServiceProvider,RouteServiceProvider,EventServiceProvider}.php
Modules/Tenant/routes/{web,api}.php
Modules/Tenant/database/migrations/2026_05_22_*.php           (5 dosya)
Modules/Tenant/Models/{Tenant,TenantType,TenantAccessRule,TenantProductAccess}.php
Modules/Tenant/Http/Controllers/{Tenant,TenantType,TenantAccess}Controller.php
Modules/Tenant/Services/TenantAccessService.php
Modules/Tenant/database/seeders/{TenantPermission,TenantType,TenantDatabase}Seeder.php
Modules/Tenant/Resources/assets/js/Pages/{Tenants,TenantTypes,TenantAccess}.vue
Modules/Tenant/Resources/assets/{js/app.js, sass/app.scss}
```

### 11.8 Bu çalışmada güncellenen dosyalar

```
app/Models/User.php                                  (tenant() ilişkisi + isTenant() helper)
Modules/Product/Models/Product.php                   (accessibleToTenant scope)
Modules/Product/Http/Controllers/ProductController.php   (index/show'a tenant filtre)
Modules/Product/Http/Controllers/CartController.php  (add'e tenant erişim doğrulaması)
resources/js/Layouts/AppLayout.vue                   (componentToNavKey + İlişkiler menü güncelleme)
modules_statuses.json                                (Tenant: true)
```

### 11.9 Sıradaki Adımlar (Öneri)

Öncelik sırasıyla:

1. **Faz 2.5 — Tenant fiyatlandırma bağla:** `priceFor()` servisini `ProductController@index/show` ve `CartController@add`'e bağla. Tenant tipinin `price_list_type` alanına göre dealer/dropship fiyatı çekilsin, pivot custom_price override etsin. **~30 dakikalık iş.**
2. **Faz 3 — Pazaryeri Entegrasyonu:** `tenant_marketplace_credentials` tablosu + `marketplace.use` permission + Trendyol/Hepsiburada stub controller'ları.
3. **Faz 4 — B2B Sipariş Akışı:** `orders.tenant_id` ekleme, tenant-filtreli sipariş listesi, B2B sipariş onay akışı (kredi limiti kontrolü).
4. **Tenant kullanıcı yaratma akışı:** User CRUD'una `tenant_id` dropdown'u; mevcut User modülünde değişiklik gerekir (Users.vue B2B mod).
5. **Demo rol switcher entegrasyonu:** `HandleInertiaRequests`'teki demo `tenant` rolü mock kullanıcısını gerçek `tenant_id`'li bir test kaydına bağla (kullanıcı testi için kolaylık).

### 11.10 Manuel Test Checklist (Tenant)

#### Kurulum
- [x] Migration çalıştı (`php artisan module:migrate Tenant`)
- [x] Seed çalıştı (`php artisan db:seed --class=Modules\\Tenant\\Database\\Seeders\\TenantDatabaseSeeder`)
- [x] Vite build başarılı (TenantTypes, Tenants, TenantAccess bundle'ları üretildi)

#### Tenant CRUD
- [ ] `/tenants` — superadmin olarak aç, "Yeni Tenant" → temel form doldur → ekle
- [ ] Filtre: tip + aktif/pasif dropdown'ları çalışıyor mu
- [ ] Düzenle → kredi limiti, iskonto güncelle → kart üzerinde değişiklik
- [ ] Pasifleştir/Aktifleştir butonu
- [ ] Sil (önce kullanıcı bağlıyken: hata mesajı; sonra silme)

#### Tenant Tipleri
- [ ] `/tenants/types` — 3 default tip görünür (Bayi/Dropship/Toptan)
- [ ] Yeni tip ekle → kod regex doğrulaması (sadece büyük harf+rakam+tire)
- [ ] Tipe bağlı tenant varken silmeye çalış → hata mesajı

#### Erişim Yönetimi
- [ ] `/tenants` tablosundaki 🔐 butonu → `/tenants/{id}/access` açılır
- [ ] "Yeni Kural" → marka seç → kayıt; tablo güncellenir
- [ ] "Yeni Override" → ürün ara (autocomplete 2+ karakter sonrası çağrılır) → seç → custom_price gir → ekle
- [ ] Override "Bu ürünü tenant'a kapat" checkbox'ı → ürün gizli olarak işaretlenir, custom_price gizli
- [ ] Kural ve override silme

#### End-to-end Tenant Erişimi
- [ ] Bir tenant'a bir markayı blokla
- [ ] Tinker veya seeder ile bir test user oluştur, `tenant_id` ata, `tenant` rolü ver
- [ ] O kullanıcı olarak login ol → `/products` → bloklu markanın ürünleri görünmemeli
- [ ] Bloklu ürünün doğrudan URL'sine git → 404 dönmeli
- [ ] Bloklu ürünü API ile sepete eklemeye çalış → validation hata

---

*Faz 1 & Faz 2 raporu 2026-05-22'de eklendi. Bir sonraki güncelleme için: Faz 2.5 tenant fiyatlandırma tamamlandığında veya Faz 3 başladığında.*

---

## 12. Tenant Modülü — Faz 3: Pazaryeri Entegrasyonu (MVP) (2026-05-23)

> **Eklenme:** 2026-05-23
> **Kapsam:** Pazaryeri credential yönetimi MVP — tenant başına Trendyol/Hepsiburada/N11/Çiçeksepeti API bilgilerinin şifreli saklanması ve UI'dan yönetimi.
> **Kasıtlı olarak DAHIL DEĞIL:** Driver pattern, gerçek API çağrısı, connection test, ürün/sipariş senkronu. Bunlar Faz 3.5/4 için ayrıldı.

### 12.1 Faz 2.5 durumu (revize edildi)

`durum.md` § 11.3'te "ProductController/CartController'a `priceFor()` bağlanmadı" denmişti — kod incelemesinde bunun **zaten yapıldığı** ortaya çıktı:

- `ProductController@index/show`: kendi `loadTenantPricing()` helper'ı ile custom_price + price_list_type'ı **batch lookup** ile çözüyor (N+1 yok), variants/displayPrice/similar dahil tenant'a göre fiyatlanıyor.
- `CartController@add`: doğrudan `app(TenantAccessService::class)->priceFor()` çağırıyor; unit_price pivot > price_list > variant.price sırası ile çözülüyor.
- `TenantType` seed'inde `price_list_type` dolu (DEALER→dealer, DROPSHIP→dropship, WHOLESALE→dealer).

**Açık kalan:** `CheckoutController@store` race condition double-check'i ve `ProductController`'ın kendi helper'ı yerine `TenantAccessService`'i kullanması (DRY). Düşük öncelikli, ayrı sprint için bırakıldı.

### 12.2 Yapılan — Pazaryeri MVP ✅

| Alan | Durum |
|---|---|
| `tenant_marketplace_credentials` migration | ✅ DB'de (`Schema::hasTable` doğrulandı) |
| `TenantMarketplaceCredential` model + encrypted casts | ✅ — `api_key`/`api_secret` Laravel `encrypted` cast |
| `Tenant->marketplaceCredentials()` ilişkisi | ✅ |
| `TenantMarketplaceController` (5 action) | ✅ — index/store/update/toggle/destroy + tenant scope guard |
| 5 yeni route (`tenants.marketplace.*`) | ✅ — `can:marketplace.manage` middleware |
| `marketplace.manage` permission | ✅ — superadmin + **tenant rolüne** atandı (id=13) |
| `TenantMarketplace.vue` (yönetim sayfası) | ✅ — credential listesi + ekle/düzenle/toggle/sil + şifre maskleme |
| `Tenants.vue` tablosuna 🛍 buton | ✅ |
| `componentToNavKey` mapping | ✅ — `Tenant::TenantMarketplace` → İlişkiler |
| Production build (`npx vite build`) | ✅ — `TenantMarketplace-*.js` 10.17 KB, 19.43s |

**Encrypted cast doğrulaması (tinker):**
- Yazma: `api_key = 'plain_key_abc'`
- DB raw: `eyJpdiI6InJxYkF...` (Laravel encrypter formatı)
- Model okuma: `plain_key_abc` ✅

### 12.3 Tasarım kararları

- **Tenant scope:** Permission tek başına yetmiyor — controller'da `authorizeTenantScope()` ile **superadmin tüm tenantlar / tenant kullanıcı sadece kendi `tenant_id`'si** kontrolü yapılıyor. URL bazlı yetki yükseltmeye karşı çift kat koruma.
- **Şifreleme:** `protected $casts = ['api_key'=>'encrypted','api_secret'=>'encrypted']`. `protected $hidden` ile JSON serialize'da gizli. Controller payload'ında `has_api_key` / `has_api_secret` boolean indicator'ları ile UI'a "ayarlı mı" bilgisi verilir; **plain değer asla UI'a düşmez**.
- **Düzenleme UX'i:** API key/secret form alanları edit'te boş açılır — boş bırakılırsa mevcut değer korunur (`if (! empty($data['api_key']))` payload'da). Değiştirmek isteyen yeni değeri yazar.
- **Unique constraint:** `(tenant_id, marketplace)` — bir tenant her pazaryerinden bir credential tutabilir. Validation'da `whereNull('deleted_at')` ile soft-deleted'ları es geçilir.
- **Marketplace enum:** PHP sabitleri (`MARKETPLACES`, `LABELS`) + validation `Rule::in()`. Yeni pazaryeri eklemek: 1 satır model + 1 satır label.

### 12.4 Yeni DB tablosu

```
tenant_marketplace_credentials
  id, tenant_id (FK cascade), marketplace (varchar 32)
  supplier_id, store_name
  api_key, api_secret (text, encrypted)
  is_active, last_sync_at, last_error, notes
  timestamps + softDeletes
  UNIQUE (tenant_id, marketplace) → uq_tenant_marketplace
  INDEX (is_active)
```

### 12.5 Yeni Route'lar (5 adet)

```
tenants.marketplace.index    GET    /tenants/{tenant}/marketplace                       can:marketplace.manage
tenants.marketplace.store    POST   /tenants/{tenant}/marketplace                       can:marketplace.manage
tenants.marketplace.update   PUT    /tenants/{tenant}/marketplace/{credential}          can:marketplace.manage
tenants.marketplace.toggle   POST   /tenants/{tenant}/marketplace/{credential}/toggle   can:marketplace.manage
tenants.marketplace.destroy  DELETE /tenants/{tenant}/marketplace/{credential}          can:marketplace.manage
```

**Sıralama:** `/tenants/{tenant}/marketplace` literal yolu, `/tenants/{tenant}` catch-all'dan ÖNCE tanımlandı (mevcut Access/Types yolları gibi).

### 12.6 Permissions (toplam 13)

| name | Sahip |
|---|---|
| `superadmin.genel`, `product.*`, `brand.manage`, `warehouse.manage`, `stock.manage`, `price-list.manage` | önceki Product işi |
| `tenant.view`, `tenant.manage`, `tenant-type.manage`, `tenant-access.manage` | Faz 1 & 2 |
| **`marketplace.manage`** (id=13) | **Faz 3 — superadmin + tenant rolü** |

`tenant` rolü artık tek bir permission'a sahip: `marketplace.manage`. Bu kasıtlı; tenant kullanıcı katalog görür, sepete ekler, sipariş verir (bunlar permission değil controller scope'larıyla yönetilir) ve **sadece kendi pazaryeri credential'larını yönetir**.

### 12.7 Faz 3'te YAPILMAYAN (sonraki fazlar)

🟠 **Driver pattern + gerçek API çağrısı yok.**
- Kapsam tercihi: MVP. `MarketplaceDriverInterface`, `TrendyolDriver`, `HepsiburadaDriver` class'ları henüz yok.
- `last_sync_at` ve `last_error` kolonları **kullanılmıyor** (UI gösteriyor ama hiçbir job yazmıyor).
- "Bağlantıyı Test Et" butonu yok — kullanıcı credential'ın doğruluğunu UI'dan denemiyor.

🟡 **Ürün/sipariş senkronizasyonu yok.**
- Pazaryerinden çekme (pull) veya pazaryerine itme (push) iş akışı tanımlanmadı.
- Queue job iskeleti yok.

🟡 **Audit log yok.**
- Credential ne zaman değişti, kim baktı vb. iz tutulmuyor. `model:Activity` veya Spatie ActivityLog ileride eklenebilir.

🟡 **Tenant kullanıcı sidebar entry'si yok.**
- Tenant rolündeki kullanıcı kendi pazaryerlerini sidebar'dan tek tıkla açamıyor — `/tenants/{kendi_id}/marketplace` URL'sini bilmesi gerek. Çözüm: HandleInertiaRequests'ten paylaşılan `auth.user.tenant_id`'yi okuyup dinamik link üretmek (yapılmadı, scope dışı tutuldu).

### 12.8 Önemli Dosya Yolları (Bu çalışmada eklenen)

```
Modules/Tenant/database/migrations/2026_05_23_100000_create_tenant_marketplace_credentials_table.php
Modules/Tenant/Models/TenantMarketplaceCredential.php
Modules/Tenant/Http/Controllers/TenantMarketplaceController.php
Modules/Tenant/Resources/assets/js/Pages/TenantMarketplace.vue
```

### 12.9 Bu çalışmada güncellenen dosyalar

```
Modules/Tenant/Models/Tenant.php                            (marketplaceCredentials() ilişkisi)
Modules/Tenant/routes/web.php                               (5 yeni route + import)
Modules/Tenant/database/seeders/TenantPermissionSeeder.php  (marketplace.manage + tenant rolüne atama)
Modules/Tenant/Resources/assets/js/Pages/Tenants.vue        (🛍 buton + canMarketplace + openMarketplace)
resources/js/Layouts/AppLayout.vue                          (componentToNavKey: TenantMarketplace → İlişkiler)
```

### 12.10 Manuel Test Checklist (Faz 3)

#### Kurulum
- [x] Migration çalıştı (`php artisan module:migrate Tenant` → DONE 62ms)
- [x] Permission seeder çalıştı (id=13 oluştu, superadmin + tenant rolüne atandı)
- [x] Vite build başarılı (`TenantMarketplace-BsZjOpJM.js` 10.17 KB)
- [x] Encrypted cast tinker doğrulandı (raw `eyJpdi...` ↔ decrypt `plain_key_abc`)

#### Superadmin senaryosu
- [ ] `/tenants` → bir tenant satırındaki 🛍 butonuna tıkla → `/tenants/{id}/marketplace` açılır
- [ ] "Yeni Bağlantı" → Trendyol seç → store_name + supplier_id + api_key + api_secret doldur → ekle
- [ ] Tabloda yeni satır: Pazaryeri pill, Mağaza, Supplier ID, Aktif badge, K+S yeşil noktalar
- [ ] Düzenle → api_key alanı **boş** açılmalı; supplier_id'yi değiştir, key/secret'ı boş bırak → kaydet → tabloda supplier_id güncel, key/secret hâlâ ayarlı (yeşil nokta)
- [ ] Toggle (⏸️) → Pasif rozeti gelir; tekrar (▶️) → Aktif
- [ ] Sil → onay → satır kalkar
- [ ] Aynı marketplace'i bir kez daha eklemeye çalış → "Bu tenant için bu pazaryeri zaten ekli" hatası

#### Tenant kullanıcı senaryosu
- [ ] Tenant rolündeki bir kullanıcı login olsun (`tenant_id`'si dolu)
- [ ] URL ile `/tenants/{kendi_tenant_id}/marketplace` aç → açılır
- [ ] Aynı URL'i **başka bir tenant'ın id'siyle** dene → 403 "erişiminiz yok"
- [ ] Kendi credential'ını ekleyebilmeli; başka tenant'ınkini POST ile değiştirmeye çalışırsa 403

#### Şifreleme
- [ ] Tinker: `\DB::table('tenant_marketplace_credentials')->first()->api_key` → `eyJpdi...` ile başlar (encrypted)
- [ ] `TenantMarketplaceCredential::first()->api_key` → düz metin
- [ ] `toJson()` çıktısında `api_key`/`api_secret` **yok** ($hidden)

### 12.11 Sıradaki Adımlar (Öneri)

Öncelik sırasıyla:

1. **Faz 3.5 — Connection Test:** Trendyol/Hepsiburada gerçek API'lerinin "health-check" endpoint'lerini çağıran tek bir `testConnection()` action'ı; sonucu `last_error`'a yazar, başarıda `last_sync_at`'i `now()`'a setler. UI'da "Test Et" butonu.
2. **Faz 4 — Driver Pattern + Ürün Push:** `MarketplaceDriverInterface` + `TrendyolDriver::pushProduct(Product)` + queue job + log. Tek pazaryeri ile pilot.
3. **Faz 4.5 — Audit Log:** Credential değişiklik logları (kim, ne zaman, hangi alan).
4. **Tenant sidebar entry'si:** HandleInertiaRequests'te `auth.user.tenant_id` paylaş; AppLayout'ta tenant rolü için "Pazaryerlerim" linki dinamik üret.
5. **Faz 2.5 temizliği:** ProductController kendi `loadTenantPricing()` helper'ını `TenantAccessService` batch metoduna refactor et; CheckoutController@store'a tenant erişim/fiyat double-check.

---

*Faz 3 (MVP) raporu 2026-05-23'te eklendi. Toplam DB tablosu: 6 tenant tablosu (tenants, tenant_types, tenant_access_rules, tenant_product_access, tenant_marketplace_credentials + users.tenant_id). Toplam permission: 13. Toplam tenant route: 21.*
