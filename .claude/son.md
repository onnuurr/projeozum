# Product Modülü — Tenant-Only B2B Satış: Eksik Analizi ve Uygulama Planı

> **Uygulama durumu (2026-07-09):** Faz 1 tamamlandı ve `claude/product-listing-module-1amdkd`
> branch'ine commit'lendi (`bdf4e42`); tüm Faz 1 testleri yeşil. Faz 2 kodu yazıldı
> (Order durum makinesi, OrderService, admin OrderController + rotalar + Orders/OrderDetail.vue,
> order.view/order.manage yetkileri, testler) ancak test koşusunda tek kök neden bulundu:
> yeni `2026_07_09_110100_add_status_indexes_to_orders_table` migration'ındaki
> `['tenant_id','status']` bileşik index'i, mevcut `2026_07_01_100000_add_tenant_id_to_orders_table`
> migration'ında zaten oluşturuluyor (duplicate index → tüm RefreshDatabase testleri düşüyor).
> **Kalan işler:** (1) yeni migration'dan bileşik index satırını çıkar (yalnız `status` index'i kalsın),
> testleri yeniden koş, Faz 2'yi commit'le; (2) Faz 3 — B2B checkout hizalama + B2C sökümü;
> (3) Faz 4 — temizlik; (4) `npm run build` + push.

## Context

Uygulama, şirketin ürettiği ürünleri (Atelier modülü üretim tarafını yönetiyor) **sadece tenantlara** (bayi/dropship müşterileri) satacak. Product modülü katalog/varyant/stok/fiyat listesi/pazaryeri altyapısına sahip ama keşifte doğrulanan kritik eksikler var:

1. **Stok bütünlüğü bozuk** (`Modules/Product/Http/Controllers/CartController.php`): sepete ekleme/adet güncelleme `product_variants.stock`'u doğrudan raw update ile düşürüyor/artırıyor — kaynak-doğruluk tablosu `stocks` atlanıyor, hiç `StockMovement` denetim kaydı yazılmıyor, terk edilen sepetler stoğu süresiz kilitliyor. `stocks.reserved_quantity` hiç kullanılmıyor. Doğru desen zaten `StockController::movement()`'ta mevcut (lockForUpdate → stocks mutasyonu → movement satırı → variant cache resync).
2. **`CheckoutService::place()` stoğa hiç dokunmuyor** — sipariş kesiminde stok düşümü ve Order referanslı StockMovement yok.
3. **`OrderItem.product_image` sahte** — `https://picsum.photos/...` placeholder (CheckoutService satır 115).
4. **Fiyat bayatlığı**: birim fiyat sepete eklemede çözülüp `cart_items.price`'a yazılıyor; place() buna güveniyor, checkout'ta yeniden fiyatlama yok.
5. **B2C kalıntıları**: tenant-only uygulamada ana domain `/checkout`, kart/taksit alanları, hardcoded promosyon kodları (TEKSTIL10/WELCOME50), `Order::TYPE_B2C`, `UserAddress`.
6. **Sipariş yaşam döngüsü yok**: `orders.status` çıplak string; admin sipariş yönetimi ekranı/rotası hiç yok (sadece portal salt-okuma). İptal → stok iade + kredi iadesi yan etkileri tanımsız.
7. **`Tenant.discount_rate` ve `payment_term_days` fiyatlama/checkout'ta kullanılmıyor.**
8. **B2B sipariş kısıtları yok** (min sipariş adedi/tutarı, koli katı).
9. Modülde Form Request neredeyse yok (inline validate), Product/Variant/Stock factory'leri yok, modül test dizinleri boş.

Ek keşif bulguları: `order_items`'ta `variant_id` kolonu yok (iptal/iade ve raporlama için gerekli); `warehouses`'ta varsayılan depo bayrağı yok; portal, Product modülünün `/cart` rotalarını subdomain üzerinden kullanıyor (sepet altyapısı B2C sökümünden etkilenmemeli).

## Kilit Tasarım Kararları

- **D1 — Sepet stoğa asla dokunmaz; sert düşüm sipariş anında.** Tüm stok mutasyonu yeni `StockService` üzerinden (tek mutasyon noktası; `StockController::movement()` gövdesinden çıkarılır). `place()` içinde `TYPE_OUT` movement + `reference = Order`. Rezervasyon/TTL yok — kredi sipariş anında çekildiği için stok da aynı anda düşer. `reserved_quantity` manuel/admin rezervasyon aracı olarak kalır; satılabilir miktar her zaman `quantity - reserved_quantity`.
- **D2 — Depo tahsisi**: `warehouses.is_default` eklenir; önce varsayılan depo, sonra kullanılabilir miktara göre azalan sırada greedy tahsis, gerekirse satır bölünür. Movement satırları tahsis kaydının kendisidir — iptal, tam olarak çekilen depolara iade eder.
- **D3 — Sipariş durum makinesi**: `STATUS_PENDING/CONFIRMED/PREPARING/SHIPPED/DELIVERED/CANCELLED` (TR etiketli). Geçişler: pending→{confirmed,cancelled}, confirmed→{preparing,cancelled}, preparing→{shipped,cancelled}, shipped→{delivered}; delivered/cancelled terminal. İptal (kargolanmadan önce): `StockService::restockForOrder()` (OUT'ları aynalayan IN movement'lar, idempotent) + `TenantCreditService::credit(reason: 'order_cancelled')`. `OrderService::transition()` geçiş haritasıyla korunur, `InvalidOrderTransitionException` fırlatır. Her geçiş `order_status_histories`'e yazılır (kalıcı iş denetimi — Prunable DEĞİL).
- **D4 — Checkout'ta yeniden fiyatlama**: `place()` transaction içinde her satırı `TenantAccessService::priceFor()` ile yeniden fiyatlar; `cart_items.price` görüntü amaçlıdır. `tenant.discount_rate` sipariş düzeyinde ara toplama uygulanır (`orders.discount_rate` + `discount_amount` kolonları — cari iskonto pratiği; satır fiyat provenance'ı temiz kalır). `payment_term_days` → `orders.due_date`.
- **D5 — B2C söküm kademeli** (CLAUDE.md disiplini): Faz 3'te rota/controller/sayfa silme + `user_addresses` → `_deprecated_20260709_user_addresses` rename (gerçek `down()`); Faz 4'te gerçek drop. `cart_items` kalır (portal kullanıyor). `orders.order_type` kolonu kalır (ileride pazaryeri siparişleri); `TYPE_B2C` yazımı Faz 3'te durur, sabit Faz 4'te silinir.
- **D6 — Kargo basitleştirme**: `standard/express/same_day` yerine `cargo` (config'ten sabit ücret, eşik üstü ücretsiz) ve `pickup` (Depodan Teslim, 0 TL). Config: `Modules/Product/config/config.php`.
- **Yetkiler**: `order.view` (listeleme/detay) + `order.manage` (durum geçişleri) — mevcut `product.view/product.add` ayrımıyla tutarlı. `ProductPermissionSeeder`'a eklenir, superadmin ikisini de alır. (AskUserQuestion teknik hata nedeniyle iletilemedi; önerilen varsayılan seçildi — uygulamada kullanıcı farklı isterse seeder değişikliği yeterli.)

---

## Faz 1 — Stok & Checkout Doğruluğu (kritik)

**Hedef:** StockService dışında stok mutasyonu kalmasın; siparişler tam denetim iziyle stok düşürsün; checkout fiyatları ve görsel snapshot'ları gerçek olsun.

1. `php artisan schema:audit` çalıştır, sonra migration'lar (her biri tek konu, gerçek `down()`):
    - `Modules/Product/database/migrations/2026_07_09_100000_add_variant_id_to_order_items_table.php` — nullable FK `product_variant_id` (nullOnDelete). color/size string'leri görüntü snapshot'ı olarak kalır.
    - `2026_07_09_100100_add_is_default_to_warehouses_table.php` — `boolean is_default default false`; tek-varsayılan kuralı controller/service'te.
2. **`Modules/Product/Services/StockService.php`** (laravel-service deseni: constructor DI, DB::transaction):
    - `move(int $variantId, int $warehouseId, string $type, int $qty, ?Model $reference, ?string $note, ?int $userId): StockMovement` — `StockController::movement()` gövdesi (lock, negatif koruması, movement satırı, variant cache resync).
    - `decrementForOrder(Order $order, Collection $lines): void` — D2 tahsisi; yetersizse yeni `Modules/Product/Exceptions/InsufficientStockException` (variant/istenen/mevcut bilgisi taşır).
    - `restockForOrder(Order $order): void` — Order referanslı OUT movement'ları okuyup aynalayan IN'ler yazar (IN'ler zaten varsa atla → idempotent).
    - `availableForVariant(int $variantId): int` — `sum(quantity - reserved_quantity)`.
3. `StockController::movement()` → `StockService::move()`'a delege (davranış birebir).
4. **`CartController` yeniden yazımı**: `decrementVariantStock()` ve tüm `increment('stock')` çağrıları silinir (add/updateQty/remove/clear); `pickVariant()` ve adet kontrolleri `StockService::availableForVariant()`'a karşı yumuşak UX kontrolü yapar (zorlama place()'te). Validasyon `Modules/Product/Http/Requests/StoreCartItemRequest.php` + `UpdateCartItemQtyRequest.php`'ye taşınır (`authorize()`: mevcut `portal.checkout`).
5. **`CheckoutService::place()` reworku**:
    - `$tenantId` zorunlu (`int`) olur, `Tenant` `type` ile yüklenir.
    - Transaction içinde satır başına: ürün+varyant yükle, `priceFor()` ile **yeniden fiyatla**, `OrderItem`'a `product_variant_id` ve gerçek kapak görseli path'ini yaz (`ProductImage` is_cover önceliğiyle; `OrderItem`'a `App\Support\Media::url()` kullanan `product_image_url` accessor'ı; DB'de ham path). picsum satırı silinir.
    - Kredi çekiminden önce `StockService::decrementForOrder()`; `InsufficientStockException` portal controller'da flash toast'a çevrilir.
    - Toplamlar sunucuda yeniden fiyatlanan satırlardan hesaplanır; controller'dan gelen `$totals` yalnız görüntü amaçlı.
6. Tek seferlik onarım komutu `Modules/Product/Console/SyncVariantStockCommand.php` (`product:sync-variant-stock`) — tüm `product_variants.stock`'u `stocks` toplamından resync (sepet operasyonları cache'i bozmuş durumda). Deploy sonrası bir kez çalıştırılır; komut ops aracı olarak kalır. Veri onarımı migration'a konmaz.
7. Factory'ler (kök `database/factories/`, `OrderFactory` konvansiyonu): `ProductFactory`, `ProductVariantFactory`, `StockFactory`, `WarehouseFactory`, `BrandFactory`, `CategoryFactory`.

**Yetki:** yeni yok. **Testler** (`tests/Feature/Product/`): `CartStockIsolationTest` (sepet işlemleri ne stocks ne variant stock'u değiştirmez, movement yazmaz), `CheckoutStockTest` (doğru depo satırları düşer, Order referanslı OUT movement'lar, oversell bloklanır, variant cache resync), `CheckoutRepriceTest` (bayat cart price yok sayılır; custom_price > price_list > variant.price sırası korunur), `CheckoutImageSnapshotTest`. Mevcut `tests/Feature/Tenant/Portal/CheckoutServiceUnitTest.php` ve portal checkout testleri yeni imzaya güncellenir.

## Faz 2 — Sipariş Yaşam Döngüsü + Admin Sipariş Yönetimi

1. Migration'lar: `create_order_status_histories_table` (`order_id` FK cascade, from_status, to_status, nullable user_id FK, nullable note, timestamps; order_id index) ve `add_status_index_to_orders_table` (index `status`, composite `tenant_id,status`).
2. `Order` modeline `STATUS_*` sabitleri, `statuses(): array` (sabit → TR etiket haritası, Vue'ya props ile), `allowedTransitions(): array`, `statusHistories()` hasMany, `scopeStatus()`. Yeni model `OrderStatusHistory` (Prunable değil).
3. **`Modules/Product/Services/OrderService.php`**: `transition(Order $order, string $to, User $actor, ?string $note): Order` — DB::transaction; harita dışı geçişte `InvalidOrderTransitionException`; `cancelled`'da `StockService::restockForOrder()` + `TenantCreditService::credit(tenant, order->total, reason: 'order_cancelled', orderId)`; history satırı yazar.
4. Admin `Modules/Product/Http/Controllers/OrderController.php`: `index` (status/tenant_id/tarih/order_no filtreleri, paginate, eager tenant+items), `show` (kalemler, durum geçmişi, kredi kayıtları), `updateStatus` (`UpdateOrderStatusRequest`: `authorize()` → `can('order.manage')`, `status` in statuses, nullable note).
5. Rotalar (`Modules/Product/routes/web.php`): `GET /orders` (`can:order.view`), `GET /orders/{order}` (whereNumber, `can:order.view`), `PUT /orders/{order}/status` (`can:order.manage`). Not: portal `/orders` subdomain'de önce tanımlı — mevcut sıra korunur.
6. Inertia sayfaları (AppLayout, `Modules/Product/Resources/assets/js/Pages/`): `Orders.vue` (tablo + durum filtre chip'leri + durum rozeti), `OrderDetail.vue` (kalemler, toplamlar, durum zaman çizelgesi, sadece `allowedTransitions` için aksiyon butonları; iptal `useModal` onayı; `useToast`). Form bileşenleri `resources/js/Components/Form`'dan (Components2 asla).
7. Yetkiler: `order.view` + `order.manage` → `ProductPermissionSeeder`; "Siparişler" menü öğesi → `Modules/Superadmin/database/seeders/MenuSeeder.php`.
8. `PortalOrderController`'a `Order::statuses()` etiket haritası geçilir (tenant TR durum adları görür; yazma yok).

**Testler:** `OrderTransitionTest` (tam matris + history satırları; illegal geçişler; shipped iptal edilemez), `OrderCancelSideEffectsTest` (bölünmüş depo tahsislerinin aynalı iadesi; kredi iadesi; çifte iptal geçiş haritasıyla bloklanır), `AdminOrdersAuthTest` (yetkisiz 403).

## Faz 3 — B2B Checkout Hizalama + B2C Sökümü

1. `schema:audit`; migration'lar:
    - `add_discount_and_due_date_to_orders_table` — `discount_rate decimal(5,2) default 0`, `discount_amount decimal(12,2) default 0`, `due_date date nullable`.
    - `Modules/Tenant/...add_min_order_total_to_tenants_table` — `min_order_total decimal(12,2) nullable`.
    - `add_order_constraints_to_products_table` — `min_order_qty` / `order_multiple` (unsignedInteger nullable).
    - `rename_user_addresses_to_deprecated` — `Schema::rename('user_addresses', '_deprecated_20260709_user_addresses')`; `down()` geri adlandırır.
2. `CheckoutService`: promo mantığı (TEKSTIL10/WELCOME50) ve kart/taksit anahtarları silinir; `computeTotals` → `quote(Tenant $tenant, iterable $items, string $shippingMethod)`: yeniden fiyatlı ara toplam, `discount_amount = round(subtotal * discount_rate/100, 2)`, D6 kargo, toplam. `place()` `discount_rate/discount_amount/due_date` kalıcılaştırır (`now()->addDays($tenant->payment_term_days)` — term > 0 ise; kredi çekimi `total` üstünden). Min sipariş kuralları: tenant `min_order_total`, satır bazında `min_order_qty`/`order_multiple` → yeni `MinimumOrderException` portal controller'da validation hatasına çevrilir.
3. Yeni config `Modules/Product/config/config.php`: `shipping.cargo_fee`, `shipping.free_shipping_target`, `shipping.methods`.
4. `Modules/Tenant/Http/Requests/StoreDropshipOrderRequest.php`: kart/taksit/promo kuralları düşer; `shipping_method in:cargo,pickup`; dropship son-müşteri adres bloğu kalır.
5. `PortalCheckoutController` + portal `Checkout.vue`: `quote()` kullanır; iskonto satırı, vade tarihi, min sipariş uyarıları, cargo/pickup seçici; promo/kart UI kaldırılır.
6. B2C yüzeyi söküm: `CheckoutController.php`, `AddressController.php`, `UserAddress.php` modeli, `Pages/Checkout.vue` silinir; `/checkout` ve `/checkout/addresses/*` rotaları kaldırılır (**`/cart/*` kalır — portal bağımlı**). `Order::TYPE_B2C` `@deprecated` işaretlenir, yazan kod kalmaz.

**Testler:** `CheckoutDiscountTest` (sipariş düzeyi iskonto, iskontolu toplam üzerinden kredi, payment_term_days'ten due_date), `MinimumOrderRulesTest`, `B2cRemovalTest` (ana domain `/checkout` 404; portal `/cart` çalışır).

## Faz 4 — Temizlik & Sertleştirme

1. Bir sürüm penceresi sonra: `drop_deprecated_user_addresses_table` migration'ı (`down()` orijinal şemayı yeniden kurar). `Order::TYPE_B2C` sabiti silinir; kalan `order_type='b2c'` satırları korumalı bir veri komutuyla ele alınır (migration'da değil).
2. Sepet hijyeni: `routes/console.php`'de schedule ile 30 günden eski `cart_items` temizliği (fiyat zaten place()'te yeniden çözüldüğü için UX hijyeni).
3. Kalan Product controller'ları Form Request'e dönüştürülür (`BrandController`, `WarehouseController`, `StockController::movement` → `StoreStockMovementRequest`, `PriceListController`) — mevcut yetki string'leri `authorize()`'da yeniden kullanılır.
4. `PriceListFactory`, `StockMovementFactory`; `StockService::move()` eski controller davranışıyla parite smoke testleri. Yeni Prunable model yok (order_status_histories kalıcı).

---

## Sıralama / risk notları

- Faz 1 deploy'u ile birlikte `php artisan product:sync-variant-stock` hemen çalıştırılmalı (geçmiş sepet tutmaları `product_variants.stock`'u bozmuş durumda).
- Faz 2, Faz 1'in `StockService` + `order_items.product_variant_id`'sine bağımlı; Faz 3 büyük ölçüde bağımsız; her faz kendi başına yayınlanabilir.
- Her migration partisinden önce `php artisan schema:audit`; her yetki string'i üç yerde aynı (rota `can:`, FormRequest `authorize()`, Vue guard) + seeder.

## Yeniden kullanılacak mevcut yapılar

- `StockController::movement()` (`Modules/Product/Http/Controllers/StockController.php`) — doğru stok mutasyon deseninin kaynağı.
- `Modules/Tenant/Services/TenantAccessService.php::priceFor()` — fiyat çözümleme (custom > price_list > variant > product).
- `Modules/Tenant/Services/TenantCreditService.php` — ledger'lı kredi charge/credit; iade `credit()` ile.
- `Modules/Tenant/Exceptions/InsufficientCreditException` — domain exception deseni.
- `App\Support\Media::url()` — görsel path → URL.
- `Modules/Product/Models/StockMovement` morphTo `reference` — Order referanslı movement'lar için hazır.

## Doğrulama

- Her fazda: `php artisan test --filter=Product` + ilgili Tenant portal testleri (`tests/Feature/Tenant/Portal/`).
- Faz 1 sonrası uçtan uca: portal subdomain'de sepete ekle → `stocks`/`product_variants.stock` DEĞİŞMEMELİ; checkout tamamla → `stocks` düşmeli, Order referanslı `stock_movements` OUT satırları oluşmalı, `order_items.product_image` gerçek path olmalı, kredi ledger'ı işlemeli.
- Faz 2 sonrası: admin `/orders` listesinde siparişi onayla→hazırla→kargola→teslim akışı; ayrı bir siparişte iptal → stok iade IN movement'ları + kredi iadesi ledger'da görünmeli.
- Faz 3 sonrası: ana domain `/checkout` 404; portal checkout'ta iskonto/vade/min-sipariş kuralları; `schema:audit` çıktısında `_deprecated_20260709_user_addresses` karantina tablosu.
- Şema değişikliklerinden önce/sonra `php artisan schema:audit` raporu karşılaştırılır.
