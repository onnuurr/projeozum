# Modül Çalışma Mantığı Revizyonu — Yol Haritası

- **Tarih:** 2026-09-22
- **Kapsam:** `Modules/{Tenant,Product,Atelier,Finance,Creative,Superadmin,Marketplace,Bagisto,PatternEngine,TrainingIngest}`
- **Durum:** **Planlama tamamlandı** — modül modül gözden geçirme + tüm açık sorular çözüldü (kod doğrulamasıyla desteklenen teknik kararlar + kullanıcı onaylı ürün kararları). **Uygulama henüz başlamadı.** Ayrıca bkz. `docs/superpowers/specs/2026-09-22-bayi-portali-kaldirma-etki-analizi.md` (Tenant §1'in detaylı etki analizi).
- **Bağlam:** `sistem kullanımı karmaşasını çözmek` başlıklı daha geniş bir işin parçası olarak (bkz. `docs/page-layout-ui-standard.md`, `docs/navigation-ia-audit.md`), kullanıcı her modülün **iş mantığını** da gözden geçirmek istedi. Bu doküman o oturumun çıktısı — kod okunarak mevcut davranış özetlendi, kullanıcı düzeltti/onayladı/yeni karar verdi.

## Nasıl okunmalı

Her modül bölümü üç kısımdan oluşuyor:
- **Mevcut durum** — kodda ne var (kısa, önceki tespitlere referans).
- **Karar** — kullanıcının onayladığı değişiklik(ler).
- **Açık sorular** — henüz netleşmemiş, uygulamadan önce cevaplanması gereken noktalar.

Hiçbir madde bu doküman yazılırken **uygulanmadı** — sıradaki adım, en yüksek etkili/bağımlı modülden (Tenant, çünkü Portal kaldırma diğer birçok modülü etkiliyor) başlayarak her biri için ayrı bir tasarım+uygulama planı çıkarmak.

---

## 1. Tenant

**Mevcut durum:** Admin tarafı (tenant CRUD, başvuru onayı, erişim kuralları, tenant tipleri) + ayrı bir self-servis Bayi Portalı (`Pages/Portal/**`: dashboard, sipariş, fatura, kredi, katalog, checkout, pazaryeri entegrasyonu, kâr hesaplayıcı, kullanıcı yönetimi).

**Kararlar:**
1. **Bayi Portalı tamamen kaldırılacak** (kod + route + tablo). `CLAUDE.md`'deki kademeli silme disiplinine göre: önce `pg_dump` yedek, sonra `_deprecated_<tarih>` karantina rename, birkaç sürüm sonra gerçek `dropIfExists`. **Bu ayrı bir etki-analizi + migration planı gerektiriyor** (bkz. Açık Sorular).
2. **Başvuru süreci 2 aşamaya bölünecek:**
   - **Aşama 1 (ön başvuru/ilk kayıt):** **SMS doğrulamalı** yeni hesap oluşturma (VatanSMS) — e-posta bu aşamada zorunlu değil, sadece telefon+SMS OTP ile doğrulanır.
   - **Aşama 2** (ön başvuru onaylanıp müşteriyle görüşüldükten sonra, gerçek bayi kaydı): vergi levhası + yasal belgeler istenir, **e-posta burada zorunlu hale gelir**. Kredi limiti ihtiyacı bu aşamada netleşir (aşağıdaki madde 3 ile bağlantılı).
3. **Kredi özelliği opsiyonel olacak.** Kartla ödemeyi tercih eden / kredi sistemini hiç kullanmayacak bayilerde `credit_limit`/`current_balance` hiç tanımlanmayabilecek — şu an her tenant'ta bu alanlar hep var (`Tenant.php` fillable), "kredi kullanmıyor" gerçek bir durum haline gelecek.
4. **Erişim kuralları / toplu alım:** Yeni bir özellik gerekmiyor — mevcut mekanizma (`TenantAccessController`: marka/kategori toptan blok + `TenantProductAccess` ürün bazlı özel fiyat/blok) zaten yeterli. Süreç: bayi önce iletişime geçer, personel görüşüp uygun görürse mevcut ekrandan elle tanımlar. Otomatik bir "toplu alım talebi" akışı **istenmiyor**.
5. **Pazaryeri entegrasyon hub'ı** (`Portal/MarketplaceHubController` + `Portal/Marketplace/{Trendyol,Hepsiburada,N11,Ciceksepeti}Controller`) Portal'la birlikte kalkıyor — yerini Bagisto'nun kendi (ayrı) sitesindeki panel alıyor (bkz. §7 Bagisto, §6 Marketplace).

**Çözülen sorular:**
- **SMS sağlayıcısı:** VatanSMS düşünülüyor (kesinleşmedi ama yön belli — entegrasyon bu varsayımla tasarlanabilir, API değişirse adaptör katmanı düşük maliyetli değişir).
- **Portal kaldırmanın etki analizi tamamlandı** — bkz. `docs/superpowers/specs/2026-09-22-bayi-portali-kaldirma-etki-analizi.md`. Özet: Portal UI + Portal'dan bağımsız çalışan pazaryeri senkron arka-plan sistemi (webhook/job/satış-gider-komisyon takibi) birlikte kalkıyor; `TenantInvoice`/`TenantCreditLedger`/`TenantPriceList`/`feed.xml` Portal'a benziyor ama kalıyor (Finance'in çekirdek akışı / bağımsız mekanizma).
- **Aşama-1 "hesap oluşturma" — onaylandı:** Başvuran kişi Aşama-1'de **sadece SMS OTP** ile doğrulanmış gerçek bir `User` hesabı alır (henüz bir `Tenant`'a bağlı değil, "beklemede" durumda), e-posta bu adımda istenmez. Aşama-2 onaylanıp gerçek bayi kaydı yapılınca **e-posta zorunlu hale gelir** ve bu kullanıcı o tenant'ın owner'ı olarak bağlanır — şu anki `TenantController::store()`'un owner-hesabı-oluşturma adımı, telefon kısmı Aşama-1'e taşınmış, e-posta kısmı Aşama-2'de kalmış şekilde ikiye bölünüyor.

---

## 2. Product

**Mevcut durum:** Katalog (ürün/varyant/görsel/SEO/AI açıklama), stok, sipariş (`Order`: `pending→confirmed→preparing→shipped→delivered`/`cancelled`, admin tarafında `store()` yok — sipariş sadece Portal checkout'tan geliyordu), sepet (`CartItem`), favori (`ProductFavorite`), pazaryeri ilan eşleme (`ProductMarketplaceListing`, `CategoryMarketplaceMapping`), fiyat listeleri (`PriceList`).

**Kararlar:**
1. **Sepet (`CartItem`) tamamen kaldırılacak.**
2. **Favoriler (`ProductFavorite`) tamamen kaldırılacak.**
3. **Sipariş oluşturma artık sadece Bagisto webhook'undan gelecek.** Portal checkout zaten kalkıyor (§1); admin tarafında da elle sipariş girme ekranı **eklenmeyecek** — `Modules/Bagisto`'nun mevcut `OrderWebhookController` + `BagistoOrderSyncService`'i (zaten var, bkz. §7) bu ihtiyacı karşılıyor.

**Çözülen sorular:**
- **Mimari ihlal çözümü:** `grep` ile doğrulandı — `Product::boms()` ilişkisi **hiçbir yerde kullanılmıyor** (`BomService` zaten `ProductBom::where('product_id', ...)` ile doğrudan sorguluyor, `$product->boms` çağrısı repo'da yok). Çözüm: `boms()` metodunu `Product.php`'den **sil** — sıfır fonksiyonel etki, `forbidden_dependencies` ihlali biter.
- **`PriceList` (Product'un kendisi) Portal'la ilgisi yok** — karıştırılmasın: bu, `ProductCard.vue`'daki (Ürün Kartı) varyant bazlı özel fiyat girişleri, tamamen admin-tarafı bir özellik (`PriceListController`, `ProductCatalogPresenter`). Portal kalkmasından etkilenmiyor, dokunulmuyor. (Açık sorudaki asıl kastedilen `TenantPriceList`'ti — o da ayrıca çözüldü, bkz. §1 ve etki analizi §2: Sanctum API üzerinden dışarıdan erişilebilir, Portal'a bağımlı değil, kalıyor.)

---

## 3. Atelier

**Mevcut durum:** Hammadde (`Material`) + reçete (`ProductBom`/`BomLine`, versiyonlu — `Boms.vue` üzerinden güncellenebiliyor) + üretim emri (`ProductionOrder`: `draft→planned→in_progress→completed`, `plan()` reçeteden otomatik malzeme/maliyet hesaplıyor + stok düşüyor, `complete()` fason+işçilik+malzeme maliyetini toplayıp birim maliyeti hesaplıyor) + üretim adımları (`ProductionOrderStep`: iç/fason, süre, fire) + Konsept Stüdyosu (AI tasarım üretimi) + Kalıp Kütüphanesi (`Pattern`, AI-metadata) + Dönüştürücü (`ConversionJob`, PDF→DXF) + Atama (`Assignment`) + Fason Tedarikçi dizini (`FasonSupplier`).

**Kararlar:**
1. **Maliyet/reçete sistemi olduğu gibi kalıyor** — kullanıcı onayladı, ek geliştirme istenmiyor.
2. **Konsept Stüdyosu + Kalıp Kütüphanesi + Dönüştürücü, mevcut `PatternEngine` modülüne taşınacak** (yeni modül açılmayacak, var olan parametrik kalıp/DXF motoruyla aynı çatı altına).
3. **Fason karar akışı:** ürün üretime hazır olunca (reçete tanım), personel "kendimiz mi üretelim, fasona mı verelim" kararını sisteme kaydedecek. Fasona verilirse: hangi fasona hangi fiyata verildiği kaydedilir, fasona **SMS ile** "sipariş geldi" bildirimi gider, süreç sistemden takip edilir. (Şu an `ProductionOrderStep.fason_supplier_id`/`step_cost` bunun bir kısmını karşılıyor; karar-kaydı + SMS bildirimi yeni.)
4. **WhatsApp entegrasyonu fikri — kesin karar değil, ileride değerlendirilecek:** fason, verdiğimiz numaraya serbest metin mesaj attığında ("kesim yapıldı", "dikiş yapıldı" gibi) sistem bunu okuyup ilgili üretim adımını (`ProductionOrderStep`) otomatik ilerletsin. Amaç: fasonun sisteme giriş yapması için ayrı bir kullanıcı hesabına ihtiyaç duymaması.

**Çözülen sorular:**
- **PatternEngine'e taşımada bağımsızlık ilkesi nasıl korunacak:** PatternEngine'in `allowed_dependencies: []` ilkesi **değişmeyecek**. Taşınan özellikler (Concept Studio/Pattern/Converter), tıpkı `CLAUDE.md`'nin zaten tanımladığı DTO istisnası deseniyle (bkz. `CLAUDE.md` "DTO kullanım istisnaları" madde 3 — PatternEngine zaten bu deseni kullanıyor) Product'a **doğrudan Eloquent ilişkisiyle değil, çağıran taraf (Atelier/Product) kendi verisinden DTO kurup PatternEngine'e vererek** bağlanacak. Yani `Pattern`/`DesignCard`/`ConversionJob` modelleri PatternEngine tarafında yaşayacak ama `product_id` gibi FK'ler yerine DTO parametreleri (ürün adı/tipi gibi düz veri) kullanacak — mevcut desenin bir devamı, yeni bir mimari kural icat edilmiyor.
- **Fason SMS + Tenant doğrulama SMS'i aynı sağlayıcı:** VatanSMS (bkz. §1), tek entegrasyon iki yerde kullanılır.
- **WhatsApp entegrasyonu — kullanıcı onayıyla resmen ertelendi ("daha sonra aktif edeceğiz").** Bu ilk uygulama dalgasının kapsamı dışında; fason karar akışı (madde 3) SMS bildirimiyle ilk sürümde tamamlanır, WhatsApp ayrı bir sonraki faz.

---

## 4. Finance

**Mevcut durum:** Proforma → Giden Fatura (gerçek e-Fatura entegrasyonu, Trendyol e-Faturam sağlayıcısı) → Alınan Fatura (tedarikçi, `production_order_id` ile Atelier üretim emrine bağlı) + Banka hesap/ekstre (MT940+CSV import) + otomatik mutabakat önerisi (`ReconciliationService`) + raporlar (ürün maliyeti, satış geçmişi, bayi alışverişi).

**Kararlar:**
1. **Alınan faturalar (`SupplierInvoice`) artık Atelier üretim emrine doğrudan bağlanmayacak.** Gerekçe: tedarikçi faturası genelde iş bittikten sonra geliyor, üretim zaten faturadan bağımsız gerçekleşmiş oluyor — fatura, üretim emrini "tetikleyen/atayan" bir mekanizma olmamalı. `production_order_id` alanının kaldırılması/kullanılmaması gerekiyor.
2. **"Bayi Alışveriş Raporu" → Bagisto sipariş raporuna dönüşecek.** Bagisto'dan gelen siparişlerin finansal özeti: hangi üründen ne kadarlık sipariş geldiği, tutar bazında. (`TenantPurchaseReportService`'in veri kaynağı değişecek, raporun kendisi **önemli, kalacak**.)

**Çözülen sorular:**
- **`production_order_id` DB'den tam olarak kaldırılacak** (sadece kullanımı bırakılmayacak) — `CLAUDE.md`'nin "kod sil, tabloyu DB'de bırakma yasak" ilkesiyle tutarlı. Gerçek `down()`'lı bir migration ile `finance_supplier_invoices.production_order_id` kolonu silinir; `SupplierInvoice.php` fillable, `Store/UpdateSupplierInvoiceRequest`, `SupplierInvoiceController`, `SupplierInvoices.vue`'daki dropdown birlikte temizlenir (doğrulandı — kullanım noktaları bunlardan ibaret, grep ile teyit edildi).
- **Bagisto sipariş raporunun veri kaynağı: `Order` tablosu.** Bagisto webhook'u zaten oraya yazıyor (bkz. §2, §8) — ayrı bir agregasyon tablosuna gerek yok, `TenantPurchaseReportService` mevcut `Order`/`OrderItem` üzerinden sorgulayacak şekilde güncellenir.

---

## 5. Creative

**Mevcut durum:** Manken+Poz üretimi (AI), Ürün Giydirme (`TryonResult`, fal.ai), insan inceleme döngüsü (onay/red + ret sebebi + review chat), **Pazarlama Görseli** (`CreativeAsset` + `CreativeTemplate`, SVG şablon tabanlı), Parça/Detay Tespiti (`GarmentScan`), Marka Kiti (`BrandKit`).

**Kararlar:**
1. **Sadece Pazarlama Görseli kısmı değişiyor.** SVG şablon sistemi (`CreativeTemplate`) kaldırılacak, yerine **tam AI-üretimi** gelecek: sistem `BrandKit`'i (logo, renkler vb.) bilip sosyal medya görsellerini otomatik üretecek. Marka bütünlüğünü korumak için "gerekli adımları kullanan" bir altyapı kurulacak — serbest/rastgele üretim değil, kontrollü.
2. Manken/Poz/Tryon/inceleme döngüsü/Parça Tespiti **değişmiyor**, olduğu gibi kalıyor.

**Açık sorular:**
- Yeni AI pazarlama görseli hattının teknik tasarımı hiç konuşulmadı: mevcut `TemplateGeneratorService`, `LayoutConstraintEngine`, `BrandTokenService`, `CreativeCopyRuleEngine` gibi servislerin ne kadarı yeniden kullanılabilir, ne kadarı sıfırdan yazılacak — bu ayrı bir tasarım oturumu gerektiriyor.
- "Marka bütünlüğü için gerekli adımlar" somut olarak ne — bir kural motoru mu (mevcut `GarmentIdentityRuleEngine` benzeri), yoksa prompt-mühendisliği seviyesinde mi kalacak? Netleşmedi.

---

## 6. Superadmin

**Mevcut durum:** Kullanıcı/rol/izin yönetimi, DB-tabanlı ayarlar, menü yönetimi (bugün üzerinde çalıştık — bkz. `docs/navigation-ia-audit.md`), bakım araçları (yedekleme, log, başarısız iş, Mimari Doktor).

**Kararlar:** Değişiklik talep edilmedi — mevcut haliyle kalıyor.

**Açık sorular:** Yok.

---

## 7. Marketplace

**Mevcut durum:** Saf entegrasyon katmanı (Eloquent yok, DTO tabanlı), Trendyol/Hepsiburada/N11/Çiçeksepeti client'ları (her biri gerçek + stub/mock versiyonuyla). Şu ana kadar Tenant Portal'ın pazaryeri hub'ı tarafından tüketiliyordu.

**Kararlar:**
1. **Tenant'lar için artık kullanılmayacak** — pazaryeri bağlantısı Bagisto'nun kendi (bu repodaki `Modules/Bagisto`'dan farklı, **ayrı bir site/panel**) tarafında yönetilecek; her tenant kendi Bagisto panelinden pazaryeri hesaplarını bağlayıp ürünlerimizi kendi ürünüymüş gibi entegre edebilecek.
2. **Kod kalacak ama pasif/beklemede.** Kaldırılmayacak — ilerde şirketin **kendi adına** (tenant'lar için değil) pazaryerlerinde doğrudan satış yapması ihtimaline karşı korunuyor; o zaman kendi ihtiyacına göre uyarlanacak.

**Açık sorular:** Yok — net bir "beklet" kararı.

---

## 8. Bagisto

**Mevcut durum:** SaaS↔Bagisto senkron motoru. Giden: Product/Stock/Tenant değişince event-listener→job kuyruğu ile Bagisto'ya push. Gelen: `OrderWebhookController` + `BagistoOrderSyncService` ile sipariş webhook alımı (zaten var).

**Kararlar:**
1. Bu modülün mevcut sipariş-webhook mekanizması, planın **merkezinde** — Tenant Portal'ın checkout'unun yerini bu alıyor (§1, §2). Yeni geliştirme **gerekmiyor gibi görünüyor**, mevcut altyapı zaten uygun.
2. Pazaryeri hesap bağlantısı da (bu modülden ayrı, tenant'ın **kendi Bagisto sitesindeki** panelden) bu ekosisteme taşınıyor — akış: pazaryeri siparişi → tenant'ın Bagisto'suna düşer → "sipariş oluştur" ile bizim sistemimize (mevcut webhook üzerinden) yansır → ödeme sonrası süreç bizim tarafta devam eder.

**Çözülen sorular:**
- **Tek paylaşımlı Bagisto sitesi** — her tenant'ın kendi ayrı Bagisto kurulumu değil, tüm bayiler tek bir Bagisto (Webkul) sitesinde, kendi hesaplarıyla çalışıyor. Bu, `OrderWebhookController`'ın tek kaynaktan (bir Bagisto instance'ı) webhook kabul etmesi yeterli demek — çoklu-kaynak doğrulama/routing mantığı **gerekmiyor**, mevcut basit webhook alıcısı mimari olarak zaten doğru varsayımla kurulu.

---

## 9. PatternEngine

**Mevcut durum:** Çocuk giyim için parametrik kalıp/DXF üretimi, ayrı Python/FastAPI mikroservisiyle konuşuyor. Beden ölçü profili yönetimi + grading (beden büyütme/küçültme) + dijitalleştirme önerisi. `allowed_dependencies: []` — hiçbir modüle bağımlı değil.

**Kararlar:**
1. **Atelier'in Konsept Stüdyosu + Kalıp Kütüphanesi + Dönüştürücü'sü buraya taşınacak** (bkz. §3).

**Çözüldü:** Bağımsızlık ilkesi korunacak, DTO deseniyle — bkz. §3'teki çözüm (aynı karar).

---

## 10. TrainingIngest

**Mevcut durum:** Creative'in giydirme üretimlerini (girdi+prompt+çıktı+karar) ayrı "training" şemasına append-only topluyor, ileride fine-tune için.

**Kararlar:** Değişiklik talep edilmedi — mevcut haliyle kalıyor.

**Açık sorular:** Yok.

---

## Konsolide açık sorular — hepsi çözüldü

| # | Soru | Çözüm |
|---|---|---|
| 1 | SMS sağlayıcısı | VatanSMS (düşünülüyor, kesinleşmedi ama tasarım bu varsayımla ilerleyebilir) |
| 2 | WhatsApp entegrasyonu | **Ertelendi** — kullanıcı onayıyla, bu fazın kapsamı dışında ("daha sonra aktif edeceğiz") |
| 3 | Product↔Atelier mimari ihlali | `Product::boms()` kullanılmıyor (grep doğrulandı) → silinecek |
| 4 | Portal kaldırmanın etki analizi | Tamamlandı, ayrı doküman: `2026-09-22-bayi-portali-kaldirma-etki-analizi.md` |
| 5 | PatternEngine bağımsızlık ilkesi | Korunacak, mevcut DTO deseniyle (`CLAUDE.md` DTO istisnası madde 3) |
| 6 | Bagisto çoklu-tenant mimarisi | Tek paylaşımlı site — webhook mimarisi değişmiyor |
| 7 | `SupplierInvoice.production_order_id` | Tam kaldırılacak (migration + tüm katmanlar) |
| 8 | `PriceList` akıbeti | Product'un kendi `PriceList`'i Portal'la ilgisiz, dokunulmuyor; `TenantPriceList` zaten §4'te (etki analizinde) çözüldü — kalıyor |
| 9 | Aşama-1 "hesap oluşturma" | Onaylandı: Aşama-1 sadece SMS OTP ile `User` oluşturur (e-posta yok), Aşama-2'de (gerçek bayi kaydı) e-posta zorunlu olur ve tenant'a bağlanır |

**Tüm 9 madde çözüldü ve onaylandı.** Planlama tamamlandı, uygulamaya geçilebilir.

## Önerilen sıralama (nihai)

1. **Tenant** — Portal kaldırma (etki analizi hazır) + 2 aşamalı başvuru + opsiyonel kredi. En çok modülü etkileyen, önce bitmeli.
2. **Product** — sepet/favori kaldırma (Portal'la aynı dalga), `boms()` mimari düzeltmesi (bağımsız, istenilen an yapılabilir, tek satır silme).
3. **Bagisto** — Portal'ın webhook/senkron mekanizmasının zaten yeterli olduğunun doğrulanması; ek geliştirme beklenmiyor.
4. **Finance** — `production_order_id` kaldırma + Bagisto sipariş raporuna geçiş. Tenant/Product'tan bağımsız yürütülebilir.
5. **Atelier → PatternEngine** taşıması + fason karar/SMS akışı. Kendi başına bir spec→plan döngüsü gerektiriyor (taşıma mekaniği + DTO sınırı tasarımı).
6. **Creative** — AI pazarlama görseli. En çok yeni tasarım gerektiren parça, ayrı bir spec oturumu hak ediyor, diğerlerinden bağımsız başlatılabilir.
7. **Superadmin / Marketplace / TrainingIngest** — değişiklik yok / beklemede, bu sırada ayrıca ele alınmayacak.

Her madde kendi spec→plan→uygulama döngüsüne girmeden önce, uygulamaya başlanacak modül için `ExitPlanMode` ile ayrı onay alınacak — bu doküman planlamayı kapatıyor, uygulamayı başlatmıyor.
