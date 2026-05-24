# 🧥 Hazır Giyim ERP - Ürün Modülü

## Proje Bilgileri
- Laravel 12 + Inertia.js + Vue 3 + Tailwind CSS
- Spatie Permission kurulu (roller/yetkiler için)
- Toast: `useToastStore` (Pinia store - `@/stores/useToast`)
- Onay Modalı: `SweetAlert2`
- Referans: Mevcut SuperAdmin modülü yapısı takip edilsin
- Dil: Türkçe yorum ve değişken isimleri

---

## 📋 ADIM 1 — Migration Dosyaları

Aşağıdaki migration dosyalarını sırayla oluştur.
Her migration'da `timestamps()` ve `softDeletes()` kullan.

### 1.1 Kategoriler
```
Tablo: categories
Alanlar:
- id
- name (string)
- slug (string, unique)
- parent_id (nullable, foreignId → categories) ← alt kategori için
- image (string, nullable)
- sort_order (integer, default 0)
- is_active (boolean, default true)
- timestamps
- softDeletes
```

### 1.2 Markalar
```
Tablo: brands
Alanlar:
- id
- name (string)
- slug (string, unique)
- logo (string, nullable)
- description (text, nullable)
- is_active (boolean, default true)
- timestamps
- softDeletes
```

### 1.3 Ana Ürünler
```
Tablo: products
Alanlar:
- id
- category_id (foreignId → categories)
- brand_id (nullable, foreignId → brands)
- name (string)
- slug (string, unique)
- description (text, nullable)
- care_instructions (text, nullable) ← yıkama talimatları
- material (string, nullable) ← kumaş içeriği
- origin_country (string, nullable, default 'TR')
- is_active (boolean, default true)
- timestamps
- softDeletes
```

### 1.4 Ürün Varyantları (Beden/Renk)
```
Tablo: product_variants
Alanlar:
- id
- product_id (foreignId → products)
- size (string) ← XS, S, M, L, XL, XXL veya 36, 38, 40...
- color (string) ← renk adı
- color_code (string, nullable) ← hex kodu #FF0000
- sku (string, unique) ← stok kodu
- barcode (string, nullable, unique)
- weight (decimal 8,2, nullable) ← gram cinsinden
- is_active (boolean, default true)
- timestamps
- softDeletes
```

### 1.5 Ürün Görselleri
```
Tablo: product_images
Alanlar:
- id
- product_id (foreignId → products)
- product_variant_id (nullable, foreignId → product_variants)
- url (string)
- alt_text (string, nullable)
- sort_order (integer, default 0)
- is_cover (boolean, default false) ← kapak fotoğrafı
- timestamps
```

### 1.6 Depolar
```
Tablo: warehouses
Alanlar:
- id
- name (string)
- code (string, unique) ← kısa kod (IST01, ANK01)
- address (text, nullable)
- city (string, nullable)
- is_active (boolean, default true)
- timestamps
- softDeletes
```

### 1.7 Stok
```
Tablo: stocks
Alanlar:
- id
- product_variant_id (foreignId → product_variants)
- warehouse_id (foreignId → warehouses)
- quantity (integer, default 0)
- reserved_quantity (integer, default 0) ← siparişte bekleyen
- min_quantity (integer, default 0) ← minimum stok uyarısı
- timestamps

NOT: product_variant_id + warehouse_id unique constraint ekle
```

### 1.8 Stok Hareketleri
```
Tablo: stock_movements
Alanlar:
- id
- product_variant_id (foreignId → product_variants)
- warehouse_id (foreignId → warehouses)
- type (enum: in/out/transfer/adjustment) ← giriş/çıkış/transfer/düzeltme
- quantity (integer)
- before_quantity (integer) ← işlem öncesi stok
- after_quantity (integer) ← işlem sonrası stok
- reference_type (string, nullable) ← Order, ProductionOrder vs
- reference_id (integer, nullable)
- note (text, nullable)
- user_id (foreignId → users)
- timestamps
```

### 1.9 Fiyat Listeleri
```
Tablo: price_lists
Alanlar:
- id
- product_variant_id (foreignId → product_variants)
- type (enum: retail/dealer/dropship) ← perakende/bayi/dropship
- price (decimal 10,2)
- currency (string, default 'TRY')
- is_active (boolean, default true)
- timestamps

NOT: product_variant_id + type unique constraint ekle
```

---

## 📋 ADIM 2 — Model Dosyaları

Her model için şunlar olsun:
- `$fillable` tanımı
- `$casts` tanımı
- Tüm ilişkiler (hasMany, belongsTo, hasOne)
- SoftDeletes trait
- `getRouteKeyName()` → slug dönsün (ürün ve kategorilerde)

### Modeller:
- `app/Models/Category.php`
- `app/Models/Brand.php`
- `app/Models/Product.php`
- `app/Models/ProductVariant.php`
- `app/Models/ProductImage.php`
- `app/Models/Warehouse.php`
- `app/Models/Stock.php`
- `app/Models/StockMovement.php`
- `app/Models/PriceList.php`

---

## 📋 ADIM 3 — Controller Dosyaları

Klasör: `app/Http/Controllers/Products/`

### 3.1 CategoryController
```
- index()   → Inertia: Products/Categories/Index
- store()   → validate + create + toast success
- update()  → validate + update + toast success  
- destroy() → soft delete, kategori bağlı ürün varsa hata ver
```

### 3.2 BrandController
```
- index()   → Inertia: Products/Brands/Index
- store()   → validate + create
- update()  → validate + update
- destroy() → soft delete, marka bağlı ürün varsa hata ver
```

### 3.3 ProductController
```
- index()   → Inertia: Products/Index (filtre: kategori, marka, is_active)
- create()  → Inertia: Products/Create (kategoriler ve markalar props olarak)
- store()   → validate + create (ürün + varyantlar + fiyatlar birlikte)
- show()    → Inertia: Products/Show
- edit()    → Inertia: Products/Edit
- update()  → validate + update
- destroy() → soft delete
```

### 3.4 WarehouseController
```
- index()   → Inertia: Products/Warehouses/Index
- store()   → validate + create
- update()  → validate + update
- destroy() → soft delete, stok varsa hata ver
```

### 3.5 StockController
```
- index()    → Inertia: Products/Stocks/Index (tüm stok durumu)
- movement() → Stok hareketi ekle (giriş/çıkış)
- history()  → Stok hareket geçmişi
```

---

## 📋 ADIM 4 — Route Tanımlamaları

`routes/web.php` içine ekle:

```php
Route::middleware(['auth'])->prefix('products')->name('products.')->group(function () {
    // Kategoriler
    Route::resource('categories', CategoryController::class)->except(['show']);
    
    // Markalar
    Route::resource('brands', BrandController::class)->except(['show']);
    
    // Ürünler
    Route::resource('/', ProductController::class);
    
    // Depolar
    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    
    // Stok
    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::post('stocks/movement', [StockController::class, 'movement'])->name('stocks.movement');
    Route::get('stocks/history', [StockController::class, 'history'])->name('stocks.history');
});
```

---

## 📋 ADIM 5 — Vue Sayfaları

Klasör: `resources/js/Pages/Products/`

### 5.1 Categories/Index.vue
```
- Kategori listesi (tablo)
- Ana kategori / Alt kategori gösterimi (tree yapı)
- Arama filtresi
- Aktif/Pasif toggle
- Ekle / Düzenle modal (SweetAlert değil, kendi modal)
- Silme → SweetAlert2 onay
- Başarı/Hata → useToastStore
```

### 5.2 Brands/Index.vue
```
- Marka listesi (kart görünümü - logo göster)
- Logo yükleme alanı
- Arama filtresi
- Aktif/Pasif toggle
- Ekle / Düzenle modal
- Silme → SweetAlert2 onay
- Başarı/Hata → useToastStore
```

### 5.3 Products/Index.vue
```
- Ürün listesi (tablo + grid görünümü toggle)
- Filtreler: Kategori, Marka, Aktif/Pasif
- Arama (isim, SKU, barkod)
- Kapak fotoğrafı thumbnail göster
- Varyant sayısını göster
- Toplam stok miktarını göster
- Düzenle / Sil butonları
- Başarı/Hata → useToastStore
```

### 5.4 Products/Create.vue & Edit.vue
```
Sekme yapısı (Tab):
  📋 Tab 1 - Temel Bilgiler:
    - Ürün adı
    - Kategori seçimi (dropdown, hiyerarşik)
    - Marka seçimi
    - Açıklama (textarea)
    - Kumaş içeriği
    - Yıkama talimatları
    - Menşei ülke
    - Aktif/Pasif

  🎨 Tab 2 - Varyantlar:
    - Beden seçimi (XS/S/M/L/XL/XXL checkbox)
    - Renk ekleme (isim + renk picker)
    - Otomatik SKU üretimi (örn: PRD001-M-RED)
    - Barkod alanı
    - Her varyant için fiyat (perakende/bayi/dropship)

  📸 Tab 3 - Görseller:
    - Fotoğraf yükleme (drag & drop)
    - Kapak fotoğrafı seçimi
    - Varyanta özel fotoğraf atama
    - Sıralama (sürükle bırak)

  📦 Tab 4 - Stok:
    - Depo seçimi
    - Her varyant için başlangıç stok girişi
    - Minimum stok uyarı seviyesi
```

### 5.5 Warehouses/Index.vue
```
- Depo listesi (kart görünümü)
- Her depodaki toplam stok miktarı
- Aktif/Pasif toggle
- Ekle / Düzenle modal
- Silme → SweetAlert2 onay
```

### 5.6 Stocks/Index.vue
```
- Stok durumu tablosu
- Filtreler: Depo, Kategori, Kritik stok
- Her varyant için: mevcut stok, rezerve, min seviye
- Kritik stok uyarısı (kırmızı vurgula)
- Stok hareketi ekle butonu (modal)
- Hareket geçmişi linki
```

---

## 📋 ADIM 6 — AppLayout Sidebar Güncellemesi

`resources/js/Layouts/AppLayout.vue` içindeki menüye ekle:

```javascript
{ icon: '👗', label: 'Ürünler',    route: 'products.index' },
{ icon: '📂', label: 'Kategoriler', route: 'products.categories.index' },
{ icon: '🏷️', label: 'Markalar',   route: 'products.brands.index' },
{ icon: '🏭', label: 'Depolar',    route: 'products.warehouses.index' },
{ icon: '📦', label: 'Stok',       route: 'products.stocks.index' },
```

---

## ⚠️ Önemli Kurallar

1. **Her zaman** `useToastStore` ile bildirim göster
2. **Silme işlemlerinde** SweetAlert2 onay modalı kullan
3. **Form hataları** inline göster (`form.errors.alan`)
4. **Soft delete** kullan, kayıtları silme
5. **SKU** otomatik üret ama kullanıcı değiştirebilsin
6. **Görseller** `storage/app/public/products/` klasörüne kaydet
7. **Slug** otomatik üret (Türkçe karakter dönüşümü yap)

---

## 🚀 Başlangıç Komutu

Claude Code terminaline şunu yaz:

```
Bu MD dosyasini oku ve ADIM 1'den baslayarak migration 
dosyalarini olustur. Her adim sonunda onayimi bekle.
```

---

## 📌 Seeder (Test Verisi)

Migration bittikten sonra şu seed verilerini ekle:

```
Kategoriler:
- Kadın Giyim
  - Üst Giyim
  - Alt Giyim  
  - Dış Giyim
- Erkek Giyim
  - Üst Giyim
  - Alt Giyim
- Çocuk Giyim

Markalar:
- (Şirket adın)

Depolar:
- Ana Depo (KOD: ANA01)

Bedenler: XS, S, M, L, XL, XXL
Renkler: Siyah, Beyaz, Lacivert, Kırmızı, Gri
```
