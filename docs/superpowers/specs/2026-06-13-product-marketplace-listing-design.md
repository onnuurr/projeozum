# Ürün–Pazaryeri Listeleme Paneli — Tasarım

**Tarih:** 2026-06-13
**Durum:** Onaylandı (implementasyona hazır)

## Bağlam ve amaç

Ürün Kataloğu tablosunda (`Modules/Product/Resources/assets/js/Pages/Products.vue`) her ürün satırında pazaryeri logoları gösteriliyor. Logoların altındaki fiyatlar şu an `MP_PRICE_FACTOR` ile site fiyatından türetilen **geçici placeholder** değerler.

Kullanıcı, bir pazaryeri logosuna tıklanınca o **ürünün ilgili pazaryerindeki listeleme durumunu** (gönderildi mi, onay durumu, pazaryeri ürün adı/kodu, kategori, fiyat, varyant eşleştirmeleri) gösteren ve düzenlemeye/kaydetmeye izin veren **detaylı bir panel** istiyor. Referans görsel: N11 "Yeni Listeleme" ekranı (`.claude/image/123456.png`).

Mevcut altyapıda ürün–pazaryeri **listeleme** verisi tutan bir yapı yok. Var olanlar: `marketplaces` (genel liste), `category_marketplace_mappings` (kategori→pazaryeri), `tenant_marketplace_credentials` (tenant API anahtarları). Bu yüzden listeleme kalıcılığı sıfırdan kurulacak.

### Kapsam kararları (kullanıcı onaylı)
- **Kalıcılık:** Yeni tablo + Kaydet. Gerçek N11/Trendyol API gönderimi **kapsam dışı** (sonraki faz); `is_sent` yalnızca yerel durum.
- **Sunum:** Sağdan açılan **drawer** (uygulamanın mevcut `MarketplaceConnectDrawer` deseniyle tutarlı).
- **Varyant eşleştirme:** Basit haliyle dahil — ürünün kendi varyantları listelenir; her satırda Stok Kodu + Barkod düzenlenebilir, "Pazaryeri Varyantı" serbest metin.
- **Form yapısı:** Tüm pazaryerleri için **tek genel form**; başlıklar dinamik (örn. "<Pazaryeri> Onay Durumu"). Her ürün+pazaryeri için ayrı kayıt.

## Veri modeli

### Tablo: `product_marketplace_listings`
Her ürün + pazaryeri için tek kayıt.

| Kolon | Tip | Açıklama |
|---|---|---|
| `id` | bigint | |
| `product_id` | FK → products, cascadeOnDelete | |
| `marketplace_id` | FK → marketplaces, cascadeOnDelete | |
| `is_sent` | boolean, default false | Pazaryerine gönderildi mi (yerel durum) |
| `sent_at` | timestamp, nullable | |
| `product_status` | string(16), default `active` | Ürün Durumu (Aktif/Pasif) |
| `approval_status` | string(16), default `not_sent` | `not_sent`/`pending`/`approved`/`rejected` |
| `store_name` | string, nullable | Mağaza (serbest metin, kaynak veri yok) |
| `model_code` | string(64), nullable | Model Kodu |
| `category_path` | string, nullable | Pazaryeri kategorisi (serbest metin) |
| `title` | string, nullable | Başlık (varsayılan: ürün adı) |
| `price` | decimal(12,2), nullable | Listeleme fiyatı (varsayılan: ürün fiyatı) |
| `currency` | string(8), default `TL` | |
| `variant_extra_price` | decimal(12,2), default 0 | Varyant Ek Fiyat |
| `delivery_template` | string, nullable | Teslimat Şablonu (serbest metin) |
| `shipping_time` | unsignedInteger, nullable | Sevkiyat Süresi (gün) |
| `variants` | json, nullable | Varyant eşleştirme satırları |
| timestamps | | |

**Kısıt:** `unique(product_id, marketplace_id)`.

`variants` JSON şekli:
```json
[{ "product_variant_id": 12, "marketplace_variant": "", "stock_code": "", "barcode": "" }]
```
Varyant eşleştirme yalnızca listeleme ile birlikte okunup yazıldığı, ayrı sorgulanmadığı için ayrı tablo yerine JSON kolon (bloat'tan kaçınma — proje DB disiplini).

**Migration:** `2026_06_13_..._create_product_marketplace_listings_table.php`. `down()` → `dropIfExists`.

### Model: `Modules/Product/Models/ProductMarketplaceListing`
- `fillable`: yukarıdaki tüm düzenlenebilir kolonlar.
- `casts`: `is_sent` bool, `sent_at` datetime, `price`/`variant_extra_price` decimal:2, `shipping_time` int, `variants` array.
- İlişkiler: `product()` belongsTo, `marketplace()` belongsTo.
- `Product` modeline `listings(): HasMany(ProductMarketplaceListing)` eklenir.

## Backend

### Controller: `Modules/Product/Http/Controllers/ProductMarketplaceListingController`
- **`show(Product $product, string $marketplace)`** → `Marketplace::where('key', $marketplace)` çözülür (yoksa 404). Mevcut listeleme `firstOrNew` ile alınır; kayıt yoksa üründen türetilmiş **varsayılan taslak** döndürülür (title=ürün adı, price=ürün fiyatı, variants=ürün varyantlarından üretilmiş satırlar). Ayrıca "Ürün Site Özeti" için ürün bilgisi (ad, marka, kategori yolu, satış/piyasa fiyatı, durum) ve ürün varyant listesi döner. JSON döner (drawer axios ile çeker).
- **`upsert(Request, Product $product, string $marketplace)`** → doğrula + `ProductMarketplaceListing::updateOrCreate(['product_id','marketplace_id'], $data)`. "Kaydet" çağrısında `is_sent=true`, `sent_at=now()`, `approval_status` `not_sent` ise `pending` yapılır. Güncellenmiş özet (price, isSent) JSON döner.

Doğrulama: status alanları `Rule::in`, fiyatlar `numeric|min:0`, `shipping_time` `integer|min:0`, `variants` array + alt alanlar nullable string / `product_variant_id` exists.

### Rotalar (`Modules/Product/routes/web.php`)
Literal `/products/*` grubunun içine (slug catch-all'dan önce):
```php
Route::get('/products/{product:id}/marketplaces/{marketplace}/listing',
    [ProductMarketplaceListingController::class, 'show'])
    ->whereNumber('product')->name('products.listings.show');
Route::put('/products/{product:id}/marketplaces/{marketplace}/listing',
    [ProductMarketplaceListingController::class, 'upsert'])
    ->whereNumber('product')->middleware('can:product.add')->name('products.listings.upsert');
```

### `ProductController@index` payload
Her ürüne `listings` özeti eklenir: `{ <marketplaceKey>: { price, isSent } }`. Bunun için `with('listings.marketplace:id,key')` eager-load edilir. Tablo, logonun altındaki fiyatı kayıt varsa gerçek listeleme fiyatından gösterir; gönderim durumunu (isSent) ikon belirginliği için kullanır.

## Frontend

### Yeni bileşen: `Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue`
- Props: `product` (satır verisi), `marketplace` (key/name/color/logoText), `open` (bool).
- Açılınca `GET /products/{id}/marketplaces/{key}/listing` ile veriyi çeker (`loading` durumu).
- Mockup düzeni:
  - Üstte "<Pazaryeri>'ne gönderilmedi/gönderildi" durum uyarısı (`is_sent`/`approval_status`'a göre renk).
  - Mağaza (input), Ürün Durumu (select Aktif/Pasif), "<Pazaryeri> Onay Durumu" (salt-okunur rozet), Model Kodu (input), Kategori (input).
  - Sağda **Ürün Site Özeti** kartı: kapak görseli, Ad, Marka, Kategori yolu, Satış Fiyatı, Piyasa Fiyatı, Satış Durumu + "Düzenle" linki (ürün edit sayfasına).
  - Başlık (input, placeholder=ürün adı), Fiyat (input + para birimi select), Varyant Ek Fiyat (input), Teslimat Şablonu (input/select placeholder), Sevkiyat Süresi (input).
  - Varyant eşleştirme tablosu: Ürün Varyantları | <Pazaryeri> Varyantları (serbest metin) | Stok Kodu | Barkod — ürün varyant satırları üzerinden `v-model`.
  - Alt: "Kaydet" (PUT) → başarıda toast + `saved` event yayar; "Kapat".
- Stil: mevcut drawer/form sınıfları ve `CustomSelect` yeniden kullanılır.

### `Products.vue` değişiklikleri
- `mp-item`'a `@click` → `openListing(p, mp)` (drawer'ı açar; `activeProduct` + `activeMarketplace` state).
- `MarketplaceListingDrawer` sayfaya eklenir; `@saved` ile ilgili ürünün `listings` özetini güncelle (fiyat + isSent), logoyu belirginleştir/soluklaştır.
- Logonun altındaki fiyat: `p.listings?.[mp.key]?.price` varsa onu, yoksa mevcut placeholder (`platformPrice`) göster. Gönderilmemişse `mp-item` soluk (opacity), gönderilmişse tam.

## İzolasyon / sınırlar
- Drawer bağımsız bir birim: girdi `product`+`marketplace`, çıktı `saved` event'i. Veriyi kendi çeker, kaydeder; Products.vue yalnızca özet günceller.
- Listeleme kalıcılığı katalog/fiyatlandırmadan ayrı; `is_sent` yerel bayrak — gerçek API gönderimi sonraki faz.

## Yetki
- Görüntüleme: katalog erişimi olan kullanıcı (index ile aynı).
- Kaydetme: `product.add` yetkisi (rota middleware).

## Test / doğrulama
1. Migration çalışır; `php artisan migrate`.
2. `/products` → bir pazaryeri logosuna tıkla → drawer açılır, ürün özeti + varsayılan taslak (kayıt yoksa) gelir.
3. Alanları doldur + Kaydet → kayıt DB'ye yazılır (`is_sent=true`), drawer kapanınca/satır özeti güncellenir, logo altı fiyat gerçek değeri gösterir.
4. Aynı logoya tekrar tıkla → kaydedilen değerler geri yüklenir.
5. Farklı pazaryeri logosu → ayrı kayıt, çakışma yok.
6. `product.add` yetkisi olmayan kullanıcı kaydedemez (403).
7. `vite build` + `php -l` temiz.

## Kapsam dışı (gelecek faz)
- Gerçek N11/Trendyol/Hepsiburada API entegrasyonu ve canlı gönderim.
- Mağaza ve Teslimat Şablonu için gerçek kaynak listeleri.
- Pazaryeri varyant kataloğu eşleştirme (şimdilik serbest metin).
