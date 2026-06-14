# Atelier (Üretim) Modülü — v1 Tasarım Dokümanı

- **Tarih:** 2026-06-14
- **Modül:** `Modules/Atelier`
- **Durum:** Tasarım onaylandı (kullanıcı), uygulama planı bekliyor
- **Bağlam:** Tekstil (çocuk giyim) firmasının üretim/atölye takibi. Daha geniş otomasyon
  vizyonunun (e-ticaret, pazaryeri, dropshipping bayileri/tenant) ilk derinlemesine ele alınan
  alt-sistemi. Diğer alt-sistemler sonraki spec→plan→uygulama döngülerinde ele alınacak.

## 1. Amaç ve kapsam

Karma (kendi atölye + fason) çocuk giyim üretiminin uçtan uca takibi: hammadde stoğu, ürün
reçetesi (BOM), esnek üretim rotası (her aşama iç ya da fason), iş emirleri, maliyet hesabı ve
biten ürünün mevcut Product stoğuna otomatik girişi.

### Kapsam içi (v1)
- Karma üretim modeli: her operasyon iç atölyede ya da fasonda yapılabilir.
- Esnek rota: operasyon kataloğu + **iş emri başına** rota (tekrar kullanılabilir şablon yok;
  "son iş emrinden rotayı kopyala" kısayolu var).
- Hammadde (kumaş/aksesuar) stoğu ve hareketleri.
- Reçete (BOM): ürün başına malzeme tüketimi.
- Maliyet roll-up: malzeme + fason ücreti + (opsiyonel) iç işçilik → birim üretim maliyeti.
- Fason = iç cari kayıt (sisteme **giriş yapmaz**).
- Biten ürün → mevcut Product `StockMovement` + `Warehouse` ile stoğa giriş.
- Varyant (beden/renk) bazında adet takibi.

### Kapsam dışı (v1) — gerekçeleriyle
- **Kalıp/kesim planı (kalıp dosyaları, pastal/serim, kumaş verimi, DXF).** İleride "AI ile model
  üretimi → kalıba göre DXF dönüşümü" yapılınca ele alınacak; o zaman `adamasantares/dxf` paketi
  geri gelecek. v1'de kesim yalnızca bir **operasyon/aşama** olarak takip edilir.
- **Fason portalı (fasoncu girişi).** v1'de fason iç cari; portal sonraki döngüde mevcut
  tenant/rol altyapısıyla eklenebilir. Veri modeli bunu engellemeyecek.
- **Tekrar kullanılabilir rota şablonları.** YAGNI; "kopyala" kısayolu yeterli. Tekrar acıtınca
  `routing_templates` eklenir.
- **Beden bazlı kumaş tüketimi (BOM beden çarpanı).** v1'de ürün-başı sabit tüketim; beden çarpanı
  ileride `bom_lines`'a eklenebilir.

## 2. Mimari kararlar

- **Hammadde Atelier'de ayrı yaşar (Yaklaşım A).** Kumaş/aksesuar Atelier'in kendi tablolarında
  (`materials` + `material_movements`). Product'taki `StockMovement` + `Warehouse` **yalnızca biten
  ürünün stoğa girişinde** kullanılır. Modül sınırı temiz: hammadde satılabilir ürün değil;
  Product/katalog domaini (örn. `scopeAccessibleToTenant`) hammaddeyle kirlenmez.
  - Reddedilen B: Product Stock'u genişletip hammaddeyi de oraya koymak → katalog kirlenir.
  - Reddedilen C: Atelier biten ürün stoğunu da kendi tutsun → iki stok gerçeği, tutarsızlık.
- **Esnek rota = operasyon kataloğu + iş emri başına rota örneği.** Şablon tablosu yok.
- **İzin:** mevcut `atelier.manage` (yalnız superadmin/iç kullanım) korunur; tek izin tüm
  route'ları kapsar. (Hafıza: Atelier yalnızca iç kullanım, tenant erişmez.)
- **CLAUDE.md disiplini:** her migration'da gerçek `down()`; hareket/log tablolarında `Prunable`;
  `schema:audit` temiz kalmalı. PostgreSQL.

## 3. Veri modeli (yeni tablolar — `Modules/Atelier`)

### Hammadde & reçete
- **`materials`** — `code`, `name`, `type` (kumaş/aksesuar/etiket…), `unit` (metre/adet/kg),
  `unit_cost`, `current_stock` (hareketlerden türetilir; performans için tutulur), `is_active`,
  timestamps.
- **`material_movements`** — `material_id` (FK), `type` (in/out/adjust), `quantity`, `unit_cost`,
  `reason` (purchase/consume/scrap/correction), `production_order_id` (nullable, tüketim izi),
  `note`, `created_by`, timestamps. **Prunable** (ör. 2 yıl eşiği).
- **`product_boms`** — `product_id` (Product FK), `name`/`version`, `is_active` (ürün başına 1 aktif
  reçete), timestamps.
- **`bom_lines`** — `bom_id` (FK), `material_id` (FK), `quantity_per_unit`, `waste_pct`.

### Operasyon & fason
- **`operations`** — `code`, `name` (Kesim/Dikim/Baskı/Nakış/Ütü/Kalite/Paket…),
  `default_location` (in_house/fason), `default_unit_cost` (iç işçilik tahmini, opsiyonel),
  `sort_order`.
- **`fason_suppliers`** — `name`, `contact_name`, `phone`, `email`, `address`, `tax_no`, `notes`,
  `is_active`, timestamps.

### İş emri (çekirdek)
- **`production_orders`** — `code`, `product_id` (FK), `warehouse_id` (Product Warehouse FK — biten
  ürün hedefi), `status` (draft/planned/in_progress/completed/cancelled), `planned_qty`,
  `produced_qty`, `planned_start`, `due_date`, `material_cost`, `fason_cost`, `labor_cost`,
  `total_cost`, `unit_cost` (türetilir), `notes`, `created_by`, timestamps.
- **`production_order_items`** — varyant kırılımı: `production_order_id` (FK),
  `product_variant_id` (FK), `planned_qty`, `produced_qty`, `scrap_qty`.
- **`production_order_steps`** — rota örneği: `production_order_id` (FK), `operation_id` (FK),
  `sequence`, `location_type` (in_house/fason), `fason_supplier_id` (nullable FK), `status`
  (pending/in_progress/done), `input_qty`, `output_qty`, `scrap_qty`, `unit_cost`
  (fason/işçilik birim ücreti), `step_cost` (türetilir), `started_at`, `completed_at`, `note`.

## 4. Akış mantığı (servisler)

- **`MaterialStockService`** — malzeme hareketi yaz + `current_stock` güncelle (in/out/adjust).
- **`BomService`** — ürün + adetten gereken malzeme miktarını hesapla (qty_per_unit × waste_pct).
- **`ProductionOrderService`** — durum geçişleri + maliyet roll-up:
  - **Açılış (`draft→planned`):** BOM × `planned_qty` → gereken malzeme; `material_movements`
    (out, reason=consume) yazılır, stok düşer. Stok yetersizse uyarı.
  - **Aşama ilerletme:** step `pending→in_progress→done`; `output_qty`/`scrap_qty` girilir;
    fason step'inde supplier + birim ücret; `step_cost` hesaplanır.
  - **Tamamlama:** son step (Kalite/Paket) bitince `FinishedGoodsService` çağrılır; `produced_qty`
    güncellenir; durum `completed`.
  - **Maliyet:** `material_cost` (BOM hareketleri) + `fason_cost` (Σ fason step) + `labor_cost`
    (Σ iç step birim) → `total_cost`; `unit_cost = total_cost / produced_qty`. Opsiyonel: `Product`
    maliyet alanına yaz.
- **`FinishedGoodsService`** — her varyant `produced_qty` için Product `StockMovement` (in) ile
  `warehouse_id`'ye giriş. Atelier → Product entegrasyon sınırı burada.

## 5. UI (Inertia/Vue, modül içi sayfalar)

Mevcut modül desenini izler: modül `Pages/`, otomatik glob, sayfa-içi sekme navigasyonu
(`AtelierNav`, Creative'deki `CreativeNav` gibi).

1. **Panel (Dashboard)** — devam eden iş emirleri, geciken işler (due_date geçmiş), fasonda
   bekleyen adımlar, düşük hammadde stoğu uyarısı.
2. **Hammaddeler** — malzeme listesi + stok seviyesi; stok giriş/çıkış/düzeltme; malzeme CRUD.
3. **Reçeteler (BOM)** — ürün seç → malzeme satırları (miktar/fire %); aktif reçete yönetimi.
4. **Operasyonlar** — operasyon kataloğu CRUD (varsayılan konum + birim işçilik).
5. **Fasoncular** — cari liste + CRUD.
6. **İş Emirleri** — liste (durum/filtre) + oluşturma sihirbazı: ürün seç → varyant adetleri
   (beden/renk matrisi) → depo seç → rota kur (operasyon ekle/sırala, iç/fason ata, "son emirden
   kopyala") → onay (malzeme gereksinimi + stok kontrolü önizlemesi).
7. **İş Emri Detayı** — aşama ilerletme paneli (step durum + miktar + fason ata), maliyet özeti
   (malzeme/fason/işçilik/birim), tamamlandığında "stoğa giriş" aksiyonu.

## 6. Test stratejisi

Servis katmanı için Feature testleri:
- Durum geçişleri (draft→planned→in_progress→completed; iptal).
- BOM malzeme düşümü (açılışta doğru miktar, stok yetersizliği uyarısı).
- Maliyet roll-up (malzeme + fason + işçilik → birim maliyet doğruluğu).
- Biten ürün stok girişi (varyant bazında Product `StockMovement`).
- CLAUDE.md migration disiplini (`down()` ters işlem; `schema:audit` temiz).

> Not: Test kapsamı uygulama sırasında gerekirse birlikte revize edilecek (kullanıcı notu).

## 7. Yan işler

- Kullanılmayan `adamasantares/dxf` paketi `composer.json`'dan kaldırılır. İleride AI model→DXF
  dönüşümü için geri eklenecek (yukarıda kapsam dışı notu).
- RBAC: `atelier.manage` izni zaten var (`AtelierPermissionSeeder`); yeni granüler izin gerekmez.

## 8. Bağımlılıklar ve entegrasyon noktaları

- **Product modülü:** `Product`, `ProductVariant`, `Warehouse`, `StockMovement` (biten ürün
  girişi). Atelier yalnızca biten ürün çıkışında Product'a yazar.
- **RBAC:** `atelier.manage` (superadmin/iç).
- **Kuyruk:** v1 senkron yeterli (manuel aksiyonlar); ağır maliyet/stok işlemleri ileride
  kuyruğa alınabilir.
