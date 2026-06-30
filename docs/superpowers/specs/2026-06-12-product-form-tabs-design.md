# Ürün Formu Sekmeli Yapı — Tasarım

Tarih: 2026-06-12
Modül: `Modules/Product`
Hedef dosya (UI): `Resources/assets/js/Pages/ProductForm.vue`

## Amaç
Tek sayfalık kart yığını olan ürün ekleme/düzenleme formunu 7 sekmeye bölerek
daha düzenli hale getirmek. Yeni sekmeler için gereken alanlar veritabanına
tam backend desteğiyle eklenir.

## Sekme → alan dağılımı

| Sekme | İçerik |
|---|---|
| Genel Bilgiler | Ürün adı, Ana SKU, Cinsiyet, Kategori, Marka, "Yeni gelen" etiketi |
| SEO | URL/slug (elle düzenlenebilir), Meta başlık, Meta açıklama, Anahtar kelimeler |
| Fiyat-Kargo | Ücretsiz kargo toggle, Toplu fiyat uygula aracı, Ağırlık (kg)/Desi, Kargo süresi, Sabit kargo ücreti |
| Stok-Varyant | Bedenler, Renkler, "Varyantları oluştur", Varyant tablosu |
| Resimler | Mevcut görseller + yeni yükleme |
| Özellikler | Kumaş içeriği, Menşei ülke, Bakım talimatları |
| Diğer | Barkod/GTIN, Yerli üretim toggle, Üretici kodu, GTIP/HS kodu |

## UI kararları
- Yapışkan üst çubuk (Kaydet/İptal) ve doğrulama hata bandı korunur.
- **Özet bloğu kaldırılır** (varyant/stok/fiyat özeti gösterilmez).
- Yan kolon (aside) kaldırılır; tüm içerik sekmeli tek alanda.
- Hata UX'i: submit'te hatalı alan içeren ilk sekmeye otomatik geçiş + sekme
  başlığında kırmızı nokta rozeti.

## Backend

### Migration — `add_seo_shipping_misc_columns_to_products_table`
products tablosuna (hepsi nullable):
- SEO: `meta_title` string(191), `meta_description` string(500), `meta_keywords` string(255)
  (slug zaten mevcut)
- Kargo: `weight` decimal(8,3), `desi` decimal(8,2), `shipping_time` string(50), `shipping_fee` decimal(10,2)
- Diğer: `barcode` string(64), `is_domestic` boolean default false, `manufacturer_code` string(64), `gtip_code` string(32)

`down()` bu kolonları gerçek şekilde dropColumn eder (CLAUDE.md migration disiplini).

### Model `Product.php`
- Yeni kolonlar `fillable`'a eklenir (`slug` dahil — elle düzenleme için).
- Cast: `is_domestic`→boolean, `weight`/`desi`/`shipping_fee`→decimal.
- Slug: `booted()` saving hook'u, slug elle girildiyse onu korur; boşsa veya ad
  değişip slug elle değişmediyse otomatik üretir.

### Controller `ProductController`
- `validateProduct`: yeni alanlar için kurallar (slug nullable unique, meta alanları
  string max, weight/desi/shipping_fee numeric min:0, is_domestic boolean, kod alanları string).
- `store` / `update`: create/update dizilerine yeni alanlar.
- `shapeProductForForm`: edit'te formu doldurmak için yeni alanları döndür.

## Kapsam dışı (YAGNI)
- Dinamik anahtar-değer özellik sistemi (ayrı tablo) — istenmedi.
- A/B varyant, sosyal yayın vb.
