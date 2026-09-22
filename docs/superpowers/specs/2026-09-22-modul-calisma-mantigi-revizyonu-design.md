# Modül Çalışma Mantığı Revizyonu — Yol Haritası

- **Tarih:** 2026-09-22
- **Kapsam:** `Modules/{Tenant,Product,Atelier,Finance,Creative,Superadmin,Marketplace,Bagisto,PatternEngine,TrainingIngest}`
- **Durum:** Kullanıcıyla modül modül gözden geçirme tamamlandı, kararlar toplandı. **Uygulama henüz başlamadı** — bu doküman, her modül için ayrı bir uygulama planına (spec→plan→uygulama döngüsü) girmeden önceki konsolide karar kaydı.
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
   - **Aşama 1 (ön başvuru):** E-posta + SMS doğrulamalı yeni hesap oluşturma.
   - **Aşama 2** (ön başvuru onaylanıp müşteriyle görüşüldükten sonra): vergi levhası + yasal belgeler istenir. **Kredi limiti ihtiyacı bu aşamada netleşir** (aşağıdaki madde 3 ile bağlantılı).
3. **Kredi özelliği opsiyonel olacak.** Kartla ödemeyi tercih eden / kredi sistemini hiç kullanmayacak bayilerde `credit_limit`/`current_balance` hiç tanımlanmayabilecek — şu an her tenant'ta bu alanlar hep var (`Tenant.php` fillable), "kredi kullanmıyor" gerçek bir durum haline gelecek.
4. **Erişim kuralları / toplu alım:** Yeni bir özellik gerekmiyor — mevcut mekanizma (`TenantAccessController`: marka/kategori toptan blok + `TenantProductAccess` ürün bazlı özel fiyat/blok) zaten yeterli. Süreç: bayi önce iletişime geçer, personel görüşüp uygun görürse mevcut ekrandan elle tanımlar. Otomatik bir "toplu alım talebi" akışı **istenmiyor**.
5. **Pazaryeri entegrasyon hub'ı** (`Portal/MarketplaceHubController` + `Portal/Marketplace/{Trendyol,Hepsiburada,N11,Ciceksepeti}Controller`) Portal'la birlikte kalkıyor — yerini Bagisto'nun kendi (ayrı) sitesindeki panel alıyor (bkz. §7 Bagisto, §6 Marketplace).

**Açık sorular:**
- SMS doğrulama sağlayıcısı henüz seçilmedi.
- Portal kaldırmanın etki analizi yapılmadı: hangi tablolar (`Portal/**` sayfalarının beslediği tüm modeller — `TenantInvoice`, `TenantCreditLedger`, `TenantMarketplaceCredential`, `MarketplaceSale`, `MarketplaceExpense`, `MarketplaceSyncLog`, `MarketplaceCommissionRate`, `CartItem`, `ProductFavorite` — bazıları Product modülünde, bkz. §2), `TenantPortalLayout` ve Portal'a özel route/middleware grubu (`routes/portal.php`), Bagisto senkronunun (`Modules/Bagisto`) Portal'a bağımlı bir parçası var mı.
- Aşama-1 "yeni hesap oluşturma" tam olarak ne demek: başvuran kişi hemen bir login hesabı mı alıyor (henüz tenant'a bağlanmadan), yoksa sadece iletişim bilgisi + doğrulama mı? Netleşmedi.

---

## 2. Product

**Mevcut durum:** Katalog (ürün/varyant/görsel/SEO/AI açıklama), stok, sipariş (`Order`: `pending→confirmed→preparing→shipped→delivered`/`cancelled`, admin tarafında `store()` yok — sipariş sadece Portal checkout'tan geliyordu), sepet (`CartItem`), favori (`ProductFavorite`), pazaryeri ilan eşleme (`ProductMarketplaceListing`, `CategoryMarketplaceMapping`), fiyat listeleri (`PriceList`).

**Kararlar:**
1. **Sepet (`CartItem`) tamamen kaldırılacak.**
2. **Favoriler (`ProductFavorite`) tamamen kaldırılacak.**
3. **Sipariş oluşturma artık sadece Bagisto webhook'undan gelecek.** Portal checkout zaten kalkıyor (§1); admin tarafında da elle sipariş girme ekranı **eklenmeyecek** — `Modules/Bagisto`'nun mevcut `OrderWebhookController` + `BagistoOrderSyncService`'i (zaten var, bkz. §7) bu ihtiyacı karşılıyor.

**Açık sorular:**
- **Mimari ihlal (flag, karar bekliyor):** `Product.php` doğrudan `Modules\Atelier\Models\ProductBom`'a `hasMany` ilişkisi kuruyor (`boms()`), ama `Modules/Product/module.json`'da `"forbidden_dependencies": ["Tenant", "Atelier"]` yazıyor. `php artisan architecture:doctor` muhtemelen bunu zaten yakalıyor. Düzeltme yönü (DI ters çevirme / ilişkiyi Atelier tarafına taşıma / forbidden_dependencies'i gözden geçirme) henüz konuşulmadı.
- `PriceList` (tenant-tipi bazlı toplu fiyat listesi) Portal kalkınca hâlâ kullanılıyor mu, yoksa o da Bagisto tarafına mı taşınıyor? Netleşmedi.

---

## 3. Atelier

**Mevcut durum:** Hammadde (`Material`) + reçete (`ProductBom`/`BomLine`, versiyonlu — `Boms.vue` üzerinden güncellenebiliyor) + üretim emri (`ProductionOrder`: `draft→planned→in_progress→completed`, `plan()` reçeteden otomatik malzeme/maliyet hesaplıyor + stok düşüyor, `complete()` fason+işçilik+malzeme maliyetini toplayıp birim maliyeti hesaplıyor) + üretim adımları (`ProductionOrderStep`: iç/fason, süre, fire) + Konsept Stüdyosu (AI tasarım üretimi) + Kalıp Kütüphanesi (`Pattern`, AI-metadata) + Dönüştürücü (`ConversionJob`, PDF→DXF) + Atama (`Assignment`) + Fason Tedarikçi dizini (`FasonSupplier`).

**Kararlar:**
1. **Maliyet/reçete sistemi olduğu gibi kalıyor** — kullanıcı onayladı, ek geliştirme istenmiyor.
2. **Konsept Stüdyosu + Kalıp Kütüphanesi + Dönüştürücü, mevcut `PatternEngine` modülüne taşınacak** (yeni modül açılmayacak, var olan parametrik kalıp/DXF motoruyla aynı çatı altına).
3. **Fason karar akışı:** ürün üretime hazır olunca (reçete tanım), personel "kendimiz mi üretelim, fasona mı verelim" kararını sisteme kaydedecek. Fasona verilirse: hangi fasona hangi fiyata verildiği kaydedilir, fasona **SMS ile** "sipariş geldi" bildirimi gider, süreç sistemden takip edilir. (Şu an `ProductionOrderStep.fason_supplier_id`/`step_cost` bunun bir kısmını karşılıyor; karar-kaydı + SMS bildirimi yeni.)
4. **WhatsApp entegrasyonu fikri — kesin karar değil, ileride değerlendirilecek:** fason, verdiğimiz numaraya serbest metin mesaj attığında ("kesim yapıldı", "dikiş yapıldı" gibi) sistem bunu okuyup ilgili üretim adımını (`ProductionOrderStep`) otomatik ilerletsin. Amaç: fasonun sisteme giriş yapması için ayrı bir kullanıcı hesabına ihtiyaç duymaması.

**Açık sorular:**
- PatternEngine'e taşıma işinin kapsamı netleşmedi: sadece Laravel tarafı mı (controller/model/route), yoksa Python/FastAPI mikroservis tarafında da bir birleşme mi olacak? PatternEngine şu an "hiçbir modüle bağımlı değil" (`allowed_dependencies: []`) — Atelier'in bu özellikleri (Product'a bağımlı, `ProductBom` üzerinden) taşınınca bu bağımsızlık ilkesi nasıl korunacak?
- Fason SMS bildirimi için sağlayıcı henüz seçilmedi (Tenant'taki SMS ihtiyacıyla aynı karar olabilir, bkz. §1).
- WhatsApp entegrasyonu: hangi sağlayıcı (WhatsApp Business API resmi mi, üçüncü parti bir servis mi), serbest metni üretim adımına eşleme mantığı (anahtar kelime mi, daha esnek bir ayrıştırma mı) — hiçbiri konuşulmadı, sadece fikir olarak not edildi.

---

## 4. Finance

**Mevcut durum:** Proforma → Giden Fatura (gerçek e-Fatura entegrasyonu, Trendyol e-Faturam sağlayıcısı) → Alınan Fatura (tedarikçi, `production_order_id` ile Atelier üretim emrine bağlı) + Banka hesap/ekstre (MT940+CSV import) + otomatik mutabakat önerisi (`ReconciliationService`) + raporlar (ürün maliyeti, satış geçmişi, bayi alışverişi).

**Kararlar:**
1. **Alınan faturalar (`SupplierInvoice`) artık Atelier üretim emrine doğrudan bağlanmayacak.** Gerekçe: tedarikçi faturası genelde iş bittikten sonra geliyor, üretim zaten faturadan bağımsız gerçekleşmiş oluyor — fatura, üretim emrini "tetikleyen/atayan" bir mekanizma olmamalı. `production_order_id` alanının kaldırılması/kullanılmaması gerekiyor.
2. **"Bayi Alışveriş Raporu" → Bagisto sipariş raporuna dönüşecek.** Bagisto'dan gelen siparişlerin finansal özeti: hangi üründen ne kadarlık sipariş geldiği, tutar bazında. (`TenantPurchaseReportService`'in veri kaynağı değişecek, raporun kendisi **önemli, kalacak**.)

**Açık sorular:**
- `SupplierInvoice.production_order_id` alanı DB'den kaldırılacak mı (migration), yoksa sadece kullanılmayacak mı (nullable kalıp UI'dan çıkarılacak)? Netleşmedi.
- Yeni "Bagisto sipariş raporu"nun tam veri kaynağı: `Order` tablosundan mı (Bagisto webhook zaten oraya yazıyor), yoksa ayrı bir agregasyon mu gerekiyor? Detay konuşulmadı.

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

**Açık sorular:**
- "Tenant'ın kendi Bagisto sitesi" ifadesi, bu repodaki `Modules/Bagisto`'dan **ayrı, bağımsız bir Bagisto (Webkul) kurulumu** anlamına geliyor — her tenant'ın kendi ayrı bir Bagisto instance'ı mı olacak, yoksa tek bir paylaşımlı Bagisto sitesi mi? Bu, `Webkul\SaasSync` paketinin (bu modülün "karşı ucu") çoklu-tenant mimarisiyle doğrudan ilgili ama hiç konuşulmadı.
- `OrderWebhookController`'ın şu an tek bir Bagisto kaynağından mı, yoksa çoklu tenant-bazlı Bagisto kaynağından mı webhook kabul ettiği doğrulanmadı — kod incelemesi gerekiyor.

---

## 9. PatternEngine

**Mevcut durum:** Çocuk giyim için parametrik kalıp/DXF üretimi, ayrı Python/FastAPI mikroservisiyle konuşuyor. Beden ölçü profili yönetimi + grading (beden büyütme/küçültme) + dijitalleştirme önerisi. `allowed_dependencies: []` — hiçbir modüle bağımlı değil.

**Kararlar:**
1. **Atelier'in Konsept Stüdyosu + Kalıp Kütüphanesi + Dönüştürücü'sü buraya taşınacak** (bkz. §3).

**Açık sorular:**
- Taşınan özellikler `Product`'a bağımlı (`Pattern`↔ürün eşleşmesi, `DesignCard`↔ürün tipi) — PatternEngine'in şu anki "hiçbir modüle bağımlı değil" ilkesi bu taşımayla nasıl korunacak (DTO sınırı mı genişleyecek, yoksa ilke mi gözden geçirilecek)? §3'teki açık soruyla aynı.

---

## 10. TrainingIngest

**Mevcut durum:** Creative'in giydirme üretimlerini (girdi+prompt+çıktı+karar) ayrı "training" şemasına append-only topluyor, ileride fine-tune için.

**Kararlar:** Değişiklik talep edilmedi — mevcut haliyle kalıyor.

**Açık sorular:** Yok.

---

## Konsolide açık sorular listesi

Uygulamaya başlamadan önce netleşmesi gerekenler, tekrar:

1. **SMS sağlayıcısı** (Tenant başvuru doğrulaması + Atelier fason bildirimi — aynı karar olabilir).
2. **WhatsApp entegrasyonu** (Atelier fason takibi) — "belki", sağlayıcı/mekanizma seçilmedi.
3. **Product↔Atelier mimari ihlali** (`Product.php::boms()`) — düzeltme yönü.
4. **Bayi Portalı kaldırmanın tam etki analizi** — hangi tablolar, hangi diğer modüller (Bagisto, Product'taki Cart/Favorite) etkileniyor, migration sırası.
5. **PatternEngine'in bağımsızlık ilkesi**, Atelier'den gelen Product-bağımlı özelliklerle nasıl bir arada duracak.
6. **Bagisto'nun çoklu-tenant mimarisi** — her tenant'ın kendi ayrı Bagisto'su mu, tek paylaşımlı site mi.
7. `SupplierInvoice.production_order_id` — DB'den kaldırılacak mı, sadece kullanılmayacak mı.
8. `PriceList`'in Portal-sonrası akıbeti.
9. Tenant başvurusunun Aşama-1'inde "hesap oluşturma"nın tam anlamı.

## Önerilen sıralama

Bağımlılık grafiğine göre: **Tenant (Portal kaldırma + başvuru akışı)** en çok modülü etkileyen karar olduğu için önce onun etki analizi + migration planı çıkarılmalı. Onu **Product** (sepet/favori kaldırma, sipariş kaynağının netleşmesi) ve **Bagisto** (webhook/senkron doğrulaması) doğal olarak takip eder. **Atelier→PatternEngine taşıması** ve **Finance**'in küçük düzeltmeleri (üretim emri bağlantısının kopması, rapor kaynağı değişimi) bağımsız yürütülebilir. **Creative**'in AI pazarlama görseli işi en çok yeni tasarım gerektiren parça olduğu için ayrı, kendi başına bir spec→plan döngüsü hak ediyor.
