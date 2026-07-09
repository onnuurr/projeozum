# Product Modülü — Tenant Satış Tamamlama Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Her migration için `laravel-migration`, servis için `laravel-service`, izin için `laravel-authorization`, test için `pest-testing` skill'i devreye alınacak.

**Goal:** Product modülünü, **yalnızca tenant'lara satış yapan** bir uygulamanın gerektirdiği doğruluk ve bütünlük seviyesine çıkarmak. Katalog/varyant/fiyat/pazaryeri tarafı olgun; kırılma **stok tutarlılığı** ve **sipariş yaşam döngüsünde**. B2C (bireysel) satış yolu tamamen kaldırılır.

**Karar (2026-07-09):** Satış kapsamı **sadece tenant**. `Order::TYPE_B2C` ve `tenantId === null` checkout yolu temizlenir; checkout tenant zorunluluğu getirir.

**Architecture:**
- **Tek stok kaynağı:** `stocks` (depo bazlı) + `stock_movements` (denetim logu) tek gerçek kaynak olur. `product_variants.stock` yalnızca `SUM(stocks.quantity)` türevidir; hiçbir yerde doğrudan yazılmaz. Rezervasyon `stocks.reserved_quantity` üzerinden yürür.
- **Rezervasyon yaşam döngüsü:** `reserve` (sepete ekle) → `commit` (sipariş) → `release` (iptal / sepet temizleme / rezervasyon süresi dolumu). Tümü yeni `StockReservationService` içinde toplanır; `CartController` ve `CheckoutService` bu servisi çağırır.
- **Sipariş durum makinesi:** `OrderStatus` enum + izinli geçiş tablosu + `order_status_histories` denetim tablosu. İptal, stok release + kredi iadesini tetikler.
- **İç sipariş yönetimi:** Satıcı (superadmin/iç kullanıcı) için `OrderController` (index/show/durum güncelle) + Inertia sayfaları + yeni `order.view` / `order.manage` izinleri.

**Tech Stack:** Laravel 11 (PostgreSQL), nwidart modüller, Inertia + Vue 3, axios, PHPUnit/Pest (RefreshDatabase, Spatie RBAC), Vite. DB disiplini: ileri-tarihli migration, gerçek `down()`, kademeli tablo silme, `schema:audit` (bkz. `CLAUDE.md`).

**Kapsam dışı:** Gerçek ödeme gateway entegrasyonu (kart alanları snapshot olarak kalır), kargo firması API'si, e-fatura.

---

## Mevcut Durum Tespiti (koddan)

| # | Sorun | Kanıt | Etki |
|---|-------|-------|------|
| 1 | Çift stok kaynağı çelişiyor | `CartController::decrementVariantStock()` yalnızca `variants.stock` düşürür; `StockController::movement()` `variants.stock = SUM(stocks.quantity)` diye yeniden hesaplar | Sepet rezervasyonları manuel stok hareketinde sessizce silinir |
| 2 | Sipariş anında stok hareketi yok | `CheckoutService::place()` yalnızca Order+OrderItem yazar, krediyi düşer | Sipariş stoğu düşürmez; iptalde iade yok |
| 3 | `reserved_quantity` atıl | `Stock` modelinde tanımlı, hiç yazılmaz | Gerçek rezervasyon yok |
| 4 | B2C yolu hedefle çelişiyor | `Order::TYPE_B2C`, `CheckoutService::place()` `tenantId=null` dalı | "Sadece tenant" hedefine aykırı |
| 5 | İç sipariş yönetimi yok | Yalnızca `Portal/PortalOrderController` (tenant-yüzlü) | Satıcı gelen siparişi yönetemez |
| 6 | `status` düz string | `orders.status varchar(32) default 'pending'` | Durum makinesi/geçmiş yok |
| 7 | `product_image` sabit picsum | `CheckoutService::place()` `"https://picsum.photos/..."` | Sipariş geçmişinde yanlış görsel |
| 8 | Totals mantığı kopya | `CheckoutController::totalsFor()` ≈ `CheckoutService::computeTotals()` | Sapma riski |
| 9 | Sıfır test | `tests/Feature`, `tests/Unit` yalnız `.gitkeep` | Para+envanter mantığı korumasız |

---

## Faz 1 — Stok Tutarlılığı & Rezervasyon (kritik)

**Hedef:** Tek stok kaynağı + gerçek rezervasyon. `variants.stock` yalnızca türetilmiş toplam.

### File Structure
- **Create** `Modules/Product/Services/StockReservationService.php` — reserve/commit/release orchestrator.
- **Create** `Modules/Product/Services/Exceptions/InsufficientStockException.php` — domain exception.
- **Modify** `Modules/Product/Http/Controllers/CartController.php` — doğrudan `variants.stock` yazımını servise devret.
- **Modify** `Modules/Product/Models/Stock.php` — rezervasyon yardımcı scope/metotları.
- **Modify** `Modules/Product/Models/ProductVariant.php` — `stock` artık salt-türev (accessor zaten var; yazımları kaldır).
- **Modify** `Modules/Product/Http/Controllers/StockController.php` — `variants.stock` recompute'unu `available` mantığıyla uyumlu tut.
- **Create** `tests/Feature/Product/StockReservationTest.php`.

### Tasarım kararları
- `stocks.reserved_quantity` = o an sepet/sipariş için ayrılmış miktar. `available_quantity = quantity - reserved_quantity` (accessor mevcut).
- **Reserve:** varyantın deposu(ları) içinde `available >= qty` olan satırda `reserved_quantity += qty` (satır `lockForUpdate`). Yetersizse `InsufficientStockException`.
- **Release:** `reserved_quantity -= qty` (0 altına düşmez).
- **Commit (sipariş):** `quantity -= qty` **ve** `reserved_quantity -= qty`; ayrıca bir `stock_movements` `TYPE_OUT` kaydı (before/after ile). Böylece denetim izi tam.
- `variants.stock` her değişimde `SUM(stocks.quantity)` ile senkronlanır — tek yazım noktası servis olur, `CartController` ve `StockController` artık ayrı ayrı yazmaz.
- **Uyumluluk notu:** `CartController` şu an "sepete ekle" anında fiziksel stok düşürüyor. Yeni modelde sepet yalnızca **rezerve** eder (fiziksel `quantity` sabit kalır), fiziksel düşüş **sipariş commit** anında olur. Bu, iptal/terk senaryolarında stok kaçağını bitirir.

- [ ] **Step 1:** `InsufficientStockException` (domain exception, `AiGenerationException` desenini izle).
- [ ] **Step 2:** `StockReservationService` — `reserve(variantId, qty)`, `release(variantId, qty)`, `commit(variantId, qty, ?userId)`, hepsi `DB::transaction` + `lockForUpdate`. Depo seçimi: `min_quantity`/id sırasına göre ilk uygun depo.
- [ ] **Step 3:** `variants.stock` senkron yardımcı metodu servise taşı; `CartController::decrementVariantStock` ve `increment('stock', …)` çağrılarını `reserve`/`release` ile değiştir.
- [ ] **Step 4:** `StockController::movement` içindeki `variants.stock` recompute'unu servisdeki senkron metoduna delege et (tek kaynak).
- [ ] **Step 5:** `StockReservationTest` — reserve yetersiz stokta patlar; reserve+release net sıfır; commit `quantity` düşürür + movement yazar; eşzamanlı iki reserve yarış testi (opsiyonel).

> **DB notu:** Bu fazda şema değişikliği yok (kolonlar mevcut). Yine de bitince `php artisan schema:audit` çalıştırılıp `variants.stock`'un artık türev olduğu doğrulanır.

---

## Faz 2 — Sipariş Yaşam Döngüsü

**Hedef:** Durum makinesi + geçmiş + iptalde stok/kredi iadesi. Checkout stok commit eder.

### File Structure
- **Create** `Modules/Product/Enums/OrderStatus.php` — enum + izinli geçişler.
- **Create** `Modules/Product/database/migrations/2026_07_09_120000_create_order_status_histories_table.php`.
- **Create** `Modules/Product/Models/OrderStatusHistory.php`.
- **Modify** `Modules/Product/Models/Order.php` — `status` cast enum'a, `histories()` ilişkisi, geçiş yardımcıları.
- **Create** `Modules/Product/Services/OrderService.php` — `transition()`, `cancel()` (stok release/commit-geri + kredi iadesi).
- **Modify** `Modules/Product/Services/CheckoutService.php` — `place()` içinde her item için `StockReservationService::commit`; picsum yerine gerçek kapak görseli snapshot'ı; başlangıç durum geçmişi yaz.
- **Create** `tests/Feature/Product/OrderLifecycleTest.php`.

### Tasarım kararları
- `OrderStatus`: `pending, confirmed, preparing, shipped, delivered, cancelled`. İzinli geçiş haritası enum içinde `transitions(): array`.
- `order_status_histories`: `order_id, from_status, to_status, note, user_id, created_at`. Log niteliğinde → `Prunable` **değil** (iş kaydı, düşük hacim, `Tenant` gibi bilerek korunur — bkz. CLAUDE.md md.5).
- `OrderService::cancel()`: yalnızca `pending/confirmed/preparing`'ten izinli. Stok: her item için commit'i geri al (`quantity += qty` + `TYPE_IN` movement). Kredi: `TenantCreditService` üzerinden iade (mevcut `charge` karşıtı `refund` — yoksa Tenant modülünde eklenmesi gerekir, ayrı not).
- `CheckoutService::place()`: rezervasyonu commit'e çevirir (sepette zaten reserve edilmişti). Böylece Faz 1 ile bütünleşir.

- [ ] **Step 1:** `OrderStatus` enum + `canTransitionTo()`.
- [ ] **Step 2:** Migration (gerçek `down()` = `dropIfExists`), `OrderStatusHistory` modeli.
- [ ] **Step 3:** `Order` modeli: `casts['status' => OrderStatus::class]`, `histories()`, `recordStatus()`.
- [ ] **Step 4:** `OrderService::transition()` + `cancel()`.
- [ ] **Step 5:** `CheckoutService::place()` — commit + görsel snapshot + ilk durum geçmişi. `product_image` = kapak görselinin `Media::url(path)`'i (yoksa null).
- [ ] **Step 6:** `OrderLifecycleTest` — geçiş kuralları, iptalde stok+kredi iadesi, geçersiz geçiş reddi.

> **Kredi iadesi bağımlılığı:** `Modules/Tenant/Services/TenantCreditService`'te `refund()` yoksa, iptal akışı için eklenir (ayrı küçük görev; Tenant modülü sahibiyle teyit).

---

## Faz 3 — İç (Satıcı) Sipariş Yönetimi

**Hedef:** Superadmin/iç kullanıcı gelen siparişleri listeler, detay görür, durum ilerletir.

### File Structure
- **Create** `Modules/Product/Http/Controllers/OrderController.php` — `index`, `show`, `updateStatus`.
- **Create** `Modules/Product/Http/Requests/UpdateOrderStatusRequest.php` — spatie `authorize()` + `Rule::enum`.
- **Modify** `Modules/Product/routes/web.php` — `products/orders` grup rotaları (slug catch-all'dan önce).
- **Modify** `Modules/Product/database/seeders/ProductPermissionSeeder.php` — `order.view`, `order.manage` izinleri.
- **Create** `Modules/Product/Resources/assets/js/Pages/Orders.vue`, `OrderDetail.vue`.
- **Create** `tests/Feature/Product/OrderManagementTest.php`.

### Tasarım kararları
- İzinler: `order.view` (liste/detay), `order.manage` (durum değiştirme). `laravel-authorization` skill'i: değişiklikten önce izin adı teyidi + `ProductPermissionSeeder`'a ekleme + tüm tüketim noktalarına (route middleware, Form Request `authorize()`, Vue guard) aynı string.
- `index`: durum/tenant/tarih filtreleri, paginate. Tenant-only olduğundan `order_type` filtresine gerek kalmaz (hepsi tenant).
- `updateStatus`: `OrderService::transition()` çağırır.

- [ ] **Step 1:** İzin seeder + `order.view/manage` (superadmin'e ata; tenant'a **verme**).
- [ ] **Step 2:** `OrderController` + `UpdateOrderStatusRequest`.
- [ ] **Step 3:** Rotalar (izin middleware'li).
- [ ] **Step 4:** `Orders.vue` + `OrderDetail.vue` (mevcut sayfa/desen: `vue-inertia-page` skill).
- [ ] **Step 5:** `OrderManagementTest` — izinsiz 403, durum güncelleme mutlu yol, geçersiz geçiş 422.

---

## Faz 4 — Tenant-only Sağlamlaştırma & Temizlik

**Hedef:** B2C yolunu kaldır, checkout tenant zorunlu, kopya mantığı tekleştir.

### File Structure
- **Modify** `Modules/Product/Models/Order.php` — `TYPE_B2C` kaldır; `order_type` tekleşince gözden geçir.
- **Modify** `Modules/Product/Services/CheckoutService.php` — `place()` `tenantId` zorunlu (int), null dalı silinir.
- **Modify** `Modules/Product/Http/Controllers/CheckoutController.php` — tenant yoksa 403/redirect; `totalsFor()` kaldır, `CheckoutService::computeTotals()` kullan.
- **Create** `Modules/Product/database/migrations/2026_07_09_130000_default_order_type_dropship.php` — `order_type` default'u `dropship`; mevcut `b2c` kayıtları varsa migration'da işaretlenir (veri kararı aşağıda).
- **Create** `tests/Feature/Product/TenantOnlyCheckoutTest.php`.

### Tasarım kararları
- `order_type`: tenant-only'de aslında tek değer kalır. **Kolonu hemen DROP etmiyoruz** (CLAUDE.md md.4 — kademeli/geri-dönülebilir). Bu fazda yalnızca default değişir + kod B2C üretmez. İleride bir sürüm sonra, `schema:audit` ile atıl doğrulanınca `_deprecated_` rename → sonra drop.
- Mevcut `b2c` order verisi: prod'da varsa **silinmez** (geçmiş sipariş). Migration yalnızca ileriye dönük default ve kod davranışını değiştirir.
- Totals: tek kaynak `CheckoutService::computeTotals()`. `CheckoutController` bunu çağırır; promo/kargo sabitleri servis içinde const kalır (ileride config'e taşınabilir — ayrı görev).

- [ ] **Step 1:** `CheckoutService::place()` imzasını `int $tenantId` yap; null dalını sil.
- [ ] **Step 2:** `CheckoutController`: tenant zorunlu guard; `totalsFor()` sil, servise delege et.
- [ ] **Step 3:** `Order` modeli: `TYPE_B2C` referanslarını temizle (kullanım aramasıyla).
- [ ] **Step 4:** Migration: `order_type` default `dropship` (gerçek `down()` = eski default'a dön).
- [ ] **Step 5:** `TenantOnlyCheckoutTest` — tenant'sız kullanıcı checkout 403; tenant mutlu yol.
- [ ] **Step 6:** `php artisan schema:audit` → `order_type`'ı atıl-aday olarak not düş (drop bir sonraki sürüme).

---

## Faz 5 — Test Kapsamı Sağlamlaştırma

**Hedef:** Para + envanter kritik yollarını Pest ile kilitle.

### File Structure
- **Create/complete** `tests/Feature/Product/` altındaki tüm fazların testleri (yukarıda listelendi).
- **Create** `Modules/Product/database/factories/` gerekli factory'ler (ProductVariant, Stock, Warehouse, Order, CartItem) — mevcut `OrderFactory`/`OrderItemFactory` var; eksikler eklenir.

- [ ] **Step 1:** Eksik factory'ler.
- [ ] **Step 2:** Erişim scope testi — `scopeAccessibleToTenant` blacklist/whitelist/kural matrisi (mevcut karmaşık mantık **hiç test edilmemiş**; regresyon riski yüksek).
- [ ] **Step 3:** Kredi bloklama — `InsufficientCreditException` mutsuz yolu.
- [ ] **Step 4:** Uçtan uca: reserve → checkout(commit) → cancel(release+refund) tam döngü.
- [ ] **Step 5:** `php artisan test --testsuite=... ` yeşil; CI için `pest-testing` skill deseni.

---

## Uygulama Sırası & Bağımlılıklar

```
Faz 1 (stok)  ──►  Faz 2 (sipariş döngüsü)  ──►  Faz 3 (iç yönetim)
                          │
                          └──►  Faz 4 (tenant-only temizlik)  ──►  Faz 5 (test)
```

- **Faz 1 önce** — diğer her şeyin doğruluğu buna bağlı.
- Faz 4'teki tenant-only guard, Faz 2 checkout değişikliğiyle birlikte yapılırsa daha az sürtünme.
- Her faz kendi testiyle kapanır; `schema:audit` şema dokunan fazlardan sonra çalışır.

## Riskler
- **Prod DB ≠ repo migration** (CLAUDE.md notu): şema dokunmadan önce `schema:audit` ile doğrula.
- **Kredi refund eksikse** Faz 2 iptal akışı Tenant modülüne küçük bir ekleme gerektirir — sahibiyle teyit.
- **Rezervasyon anlamı değişiyor** (Faz 1): sepet artık fiziksel stok düşürmüyor; mevcut sepetlerde/verilerde geçiş anında `reserved_quantity` yeniden hesaplanmalı (tek seferlik reconcile komutu düşünülebilir).
