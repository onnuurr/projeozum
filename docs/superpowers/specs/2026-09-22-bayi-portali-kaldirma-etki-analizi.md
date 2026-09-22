# Bayi Portalı Kaldırma — Etki Analizi

- **Tarih:** 2026-09-22
- **Kapsam:** `Modules/Tenant` Portal alt-sistemi + bağlı pazaryeri senkron altyapısı + `Modules/Product`'ın sepet/favori özellikleri
- **Durum:** Salt-okuma analiz — **hiçbir kod/route/tablo değişmedi**. `docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md`'nin §1 açık sorusuna cevap.
- **Karar (bu oturumda netleşti):** Pazaryeri senkron altyapısı (webhook alıcıları + zamanlanmış job'lar + satış/gider/komisyon takibi) **tamamen kalkıyor** — sadece Portal arayüzü değil. Bagisto'nun kendi (ayrı, tek paylaşımlı) sitesi artık pazaryeri bağlantısını da kendisi yönetecek.

---

## 1. Kaldırılacaklar

### 1a. Portal arayüzü (Vue + Controller + Route)
- `Modules/Tenant/Resources/assets/js/Pages/Portal/**` — 17 sayfa (Dashboard, Orders, OrderDetail, Invoices, InvoiceDetail, Credit, Catalog, CatalogProduct, Checkout, Financials, ProfitCalculator, Users/Index, Marketplace/{ComingSoon,Index}, Marketplace/Trendyol/{Dashboard,Listings,CategoryMapping}).
- `resources/js/Layouts/TenantPortalLayout.vue`.
- `Modules/Tenant/Http/Controllers/Portal/**` — 15 controller (`PortalDashboardController`, `PortalOrderController`, `PortalInvoiceController`, `PortalCreditController`, `PortalCatalogController`, `PortalCheckoutController`, `PortalFinancialsController`, `PortalProfitController`, `PortalUserController`, `MarketplaceHubController`, `TenantFeedController` **HARİÇ** — bkz. §2), `Marketplace/{Trendyol,Hepsiburada,N11,Ciceksepeti}Controller`.
- `Modules/Tenant/routes/portal.php` (102 satır, tüm içeriği).
- `routes/web.php`:18-21 — portal subdomain route mount bloğu (feed.php mount'u, §2'de açıklandığı gibi **kalıyor**, sadece portal.php mount'u gidiyor).
- Middleware (sadece Portal'a özel, başka hiçbir yerde kullanılmıyor — doğrulandı): `Modules\Tenant\Http\Middleware\EnsureUserActive` (`active`), `ResolveTenantFromSubdomain` (`tenant.subdomain`), `BlockSuperadminFromMarketplace` (`marketplace.tenant-only`) — `bootstrap/app.php`'daki 3 alias kaydı da birlikte kalkar.
- `portal.*`/`marketplace.*` izin (permission) tanımları — Superadmin'in Roller/İzinler ekranından da kaybolur.

### 1b. Pazaryeri senkron arka-plan sistemi (Portal UI'dan bağımsız, ama sadece Portal'ın beslediği veriyle çalışıyordu)
- `Modules/Tenant/Http/Controllers/Api/Webhooks/{Trendyol,Hepsiburada,N11,Ciceksepeti}WebhookController.php` — pazaryeri platformlarının doğrudan çağırdığı canlı webhook uçları.
- `Modules/Tenant/routes/api.php`:17-21 — bu 4 webhook route'u.
- `Modules/Tenant/Services/Marketplace/**` (`MarketplaceSyncOrchestrator`, `MarketplaceClientGateway`, `TenantMarketplaceSyncService`) — zamanlanmış (scheduler) senkron dispatcher.
- `Modules/Tenant/Jobs/Marketplace/**` (`Pull{Trendyol}OrdersJob`, `SyncTrendyolProductsJob`, `PullTrendyolReportsJob` vb.).
- Modeller: `TenantMarketplaceCredential`, `MarketplaceSale`, `MarketplaceSyncLog`, `MarketplaceExpense`, `MarketplaceCommissionRate` (+ `Modules/Tenant/database/seeders/MarketplaceCommissionSeeder.php`).
- `Modules/Tenant/Services/ProfitCalculatorService.php` — sadece bu verilerle çalışıyorsa o da gider (aşağıda doğrulama gerekiyor, bkz. §4).

### 1c. Product modülü — sepet & favori (ayrı kararla zaten netti, aynı dalgada gidiyor)
- `Modules/Product/Models/CartItem.php`, `Http/Controllers/CartController.php`, `Services/CheckoutService.php`, `Console/PruneStaleCartsCommand.php` (+ `routes/console.php`'daki zamanlaması).
- `Modules/Product/Models/ProductFavorite.php`, `Http/Controllers/FavoriteController.php`. **Not:** admin `ProductController.php` da bu modeli import ediyor — muhtemelen ürün listesinde favori sayısı gösteriyor, silmeden önce bu kullanım noktası tek tek kontrol edilmeli.

### Kaldırılacak tablolar (kademeli — CLAUDE.md disiplini: yedek → `_deprecated_<tarih>` karantina → gerçek `dropIfExists`)
| Tablo | Migration dosyası |
|---|---|
| `cart_items` | `2026_05_17_160000_create_cart_items_table.php` (+ `..._180000_add_variant_id...`) |
| `product_favorites` | `2026_05_17_150000_create_product_favorites_table.php` |
| `tenant_marketplace_credentials` | `2026_05_23_100000_...` |
| `marketplace_sales` | `2026_07_03_100000_...` |
| `marketplace_expenses` | `2026_07_03_100100_...` |
| `marketplace_sync_logs` | `2026_07_03_100200_...` |
| `marketplace_commission_rates` | `2026_07_03_100300_...` |

---

## 2. Kalacaklar — Portal'a benziyor ama Portal değil, karıştırılmamalı

- **`Modules/Tenant/routes/feed.php` + `TenantFeedController` (`feed.xml`)** — token-tabanlı, girişsiz, Google Merchant XML formatında ürün feed'i. Aynı `{slug}.portal_domain` subdomain'ini kullanıyor ama **ayrı bir route grubu**, Portal middleware'lerine (`auth`/`can:portal.access`) bağlı değil. Bu, tenant'ın kendi Bagisto sitesinin ürünlerimizi **çekebileceği** doğal köprü olabilir — **kalmalı**, muhtemelen Bagisto entegrasyonunda daha da önemli hale gelecek.
- **`TenantInvoice` ve tüm bağlı kod** (`TenantInvoiceApiController`, `TenantInvoicePolicy`, `Finance\Services\TenantPurchaseReportService`, `OutgoingInvoice`/`ProformaInvoice`'ın `tenant_invoice_id` bağı, `ReconciliationService`) — Portal-özel değil, Finance'in çekirdek faturalama akışının parçası. Tenant kendi faturasını Portal'dan **görmeyecek** ama fatura kaydı sistemde tutulmaya devam edecek.
- **`TenantCreditLedger`** — admin `OrderController`'da (Product modülü) zaten kullanılıyor, Portal'a özel değil. "Kredi özelliği opsiyonel" kararıyla uyumlu: kredi kullanan bayilerde bu tablo canlı kalmaya devam edecek.
- **`TenantPriceList`** — `TenantPriceListApiController` (genel `Api/v1` altında, `auth:sanctum` ile) + `TenantService` kullanıyor, Portal'a özel değil. **Açık soru:** bu API dışarıdan (belki Bagisto'dan) mı çağrılacak, yoksa o da mı işlevsiz kalacak? Netleşmedi, şimdilik dokunulmuyor.
- **`Order` modeli/tablosu (Product)** — zaten önceki kararla sabit: Portal checkout gidiyor ama `Order` kalıyor, kaynağı Bagisto webhook'u oluyor.

---

## 3. Doğrudan etkilenmeyen ama komşu modüller
- **`Modules/Bagisto`** — mevcut push/webhook mekanizması zaten bu planın merkezinde, herhangi bir kod değişikliği gerektirmiyor (bkz. ana yol haritası §7-8).
- **`Modules/Marketplace`** (DTO-tabanlı Trendyol/Hepsiburada/N11/Çiçeksepeti client'ları) — bu, Tenant modülündeki §1b'de bahsedilen senkron sisteminden **ayrı bir kod tabanı**. §1b'nin kalkmasından etkilenmiyor; zaten ayrı kararla "beklemede" bırakılmıştı (bkz. yol haritası §7).
- **`Modules/Superadmin`** — Menüde "Pazaryeri" ile ilgili bir alt-öğe yok (bugünkü menü denetiminde görülmedi), ama Roller/İzinler ekranındaki `portal.*`/`marketplace.*` izinleri temizlenmeli.

---

## 4. Uygulamadan önce doğrulanması gerekenler (mekanik ama atlanmamalı)
1. `Modules/Product/Http/Controllers/ProductController.php`'nin `ProductFavorite` kullanım noktası — silme öncesi tek tek incelenmeli.
2. `Modules/Tenant/Services/ProfitCalculatorService.php` (Portal'ın "Kâr Marjı Hesaplayıcı"sının servis katmanı) — sadece marketplace-sync verisiyle mi çalışıyor, yoksa genel bir hesaplama mantığı olarak başka yerde de kullanılabilir mi? Silme öncesi kontrol edilmeli.
3. `config('app.portal_domain')` / `PORTAL_DOMAIN` env değişkeni — feed.xml hâlâ buna bağlı olduğu için **silinmeyecek**, sadece artık portal.php'yi değil feed.php'yi beslediği netleştirilecek (yorum satırı güncellemesi yeterli).
4. `TenantServiceProvider`/`RouteServiceProvider`'daki Portal'a referans veren yorumlar güncellenmeli (kod değil ama okunabilirlik).
5. Mimari Doktor (`php artisan architecture:doctor`) kaldırma sonrası çalıştırılıp yeni orphan/broken referans çıkmadığı doğrulanmalı.

## 5. Önerilen uygulama sırası
1. Önce **salt-okuma doğrulama** (§4'teki 2 madde — ProductController/ProfitCalculatorService).
2. Kod kaldırma tek commit'te değil, katman katman: (a) Portal Vue sayfaları + Layout, (b) Portal controller+route, (c) marketplace webhook+job+servis katmanı, (d) Product sepet/favori.
3. Her katmandan sonra `php artisan route:list` ile referans kalmadığı, `@vue/compiler-sfc` ile dokunulan dosyaların statik doğrulaması yapılır.
4. Tablo silme CLAUDE.md'nin 3 aşamasına göre **ayrı, sonraki bir oturumda**: önce `pg_dump` yedek → `_deprecated_20260922` rename → bir-iki sürüm sonra gerçek `dropIfExists`. Bu doküman kapsamında tablo silme migration'ı **yazılmadı**, sadece hangi tabloların etkileneceği tespit edildi.
