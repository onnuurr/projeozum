# Architecture Doctor — Faz 2-6 Planı

## Context

Faz 0 (Rule Engine iskeleti) ve Faz 1 (Deptrac modül sınırı denetimi) uygulandı ve
çalışıyor. Bu plan, doctor-v6'nın önerdiği sıralamayla (Migration → AI Layer → Controller
→ Tenant Isolation → Read-only Quality) kalan 5 fazı kapsıyor.

Her faz için önce gerçek kod tabanını araştırdım (3 paralel Explore ajanıyla: migration
disiplini + `schema:audit`/Prunable, AI katmanı contract/binding kalıpları, controller/tenant
izolasyon kalıpları). Araştırma, doctor.md'nin orijinal genel öneri listesinin bazı
maddelerinin BU projeye uymadığını ortaya çıkardı — plan bunları körü körüne uygulamak
yerine gerçek bulgulara göre budandı:

- **Gerçek bir kural ihlali bulundu**: `Modules/Product/Providers/ProductServiceProvider.php:28`
  `ProductDescriptionGenerator` contract'ını hiçbir Mock alternatifi olmadan, config kontrolü
  bile yapmadan doğrudan `GeminiProductDescriptionGenerator`'a bağlıyor — Creative/Atelier'in
  her ikisinin de sahip olduğu config-tabanlı Gemini/Mock seçim deseninin dışında. Product'ın
  hiç testi bu contract'ı hiç kullanmıyor. Bu, Faz 3'ün gerekçesini somutlaştırıyor.
- **Ölü kod bulundu**: `app/Models/Traits/BelongsToTenant.php` bir global scope tanımlıyor
  ama HİÇBİR model bunu kullanmıyor — tenant filtrelemesi %100 manuel
  (`scopeAccessibleToTenant`/`accessibleToTenant()` çağrıları veya Policy'de `tenant_id`
  karşılaştırması). Bu, Faz 5'in önermesini ("eksik scope gerçek bir risk") doğruluyor.
- **Orijinal wishlist'ten iki madde düşürüldü**: `.claude/skills/laravel-controller/SKILL.md`
  açıkça çoklu-tablo işlemlerinde controller içinde `DB::transaction` kullanımına izin
  veriyor (örnek: `ProductController::store`) ve tekli Eloquent create/update için Service
  şartı koşmuyor. doctor.md'nin "Controller içinde transaction" ve "Controller içinde
  create/update" maddeleri bu projenin kendi onayladığı konvansiyonla çelişiyor — bu yüzden
  Faz 4'e alınmadı (aşağıda gerekçesiyle birlikte).

## Ortak Tasarım Kararları (tüm fazlar için geçerli)

- **Dosya yerleşimi**: `tools/architecture-doctor/app/Rules/<Kategori>/<Ad>Rule.php` —
  RuleDiscovery zaten recursive taradığı için motor hiç değişmiyor (doctor-v6'nın "yeni ihtiyaç
  → yeni Rule, altyapı değişmez" ilkesi burada da geçerli kalıyor).
- **Hepsi `Maturity::Experimental` olarak başlar** — Faz 1'de kurulan lifecycle mekanizması
  sayesinde hiçbiri gönderildiği gün `--ci`'yi bloklamıyor; birkaç gün false-positive
  çıkmadığı görülünce tek tek `Maturity::Stable`'a yükseltilir.
- **Migration kuralları için baseline yerine "introducedIn tarihi" kullanılıyor**: Deptrac'ın
  aksine bu kurallar deptrac'ın native baseline mekanizmasına sahip değil. Migration
  dosyaları zaten `YYYY_MM_DD_HHMMSS_` ile damgalı olduğundan, geçmiş migration'ları
  yeniden yargılamak yerine kural yalnızca **kuralın kendi `lifecycle().introducedIn`
  tarihinden SONRA oluşturulmuş** migration dosyalarını değerlendirir — 137 mevcut
  migration otomatik olarak muaf kalır, ayrı bir baseline dosyası gerekmez.

---

## Faz 2 — Migration Discipline

CLAUDE.md'nin "Veritabanı değişiklik disiplini" bölümünü ve onu birebir kodlayan
`.claude/skills/laravel-migration/SKILL.md`'yi otomatik denetime çeviriyor — yeni politika
değil, mevcut skill'in checklist'inin uygulanması.

**Bulgular**: 137 migration'ın hiçbirinde boş `down()` yok (kural ileriye dönük bir muhafız).
Kanonik örnekler zaten repo'da: `Modules/Product/database/migrations/2026_05_17_140100_drop_legacy_variant_tables.php`
(gerçek `down()` — ama bu dosya, kademeli-drop disiplini CLAUDE.md'ye yazılmadan ÖNCEKİ bir
migration olabilir, bu yüzden Faz 2'nin drop-kuralı bunu retroaktif cezalandırmaz — introducedIn
tarihi zaten bunu otomatik hallediyor) ve `database/migrations/2026_06_09_120000_quarantine_legacy_orphan_tables.php`
(rename-to-`_deprecated_<tarih>` deseninin gerçek örneği).

### Kurallar

1. **`MigrationDownIsRealRule`** — `Severity::Critical`. Her migration dosyasını parse edip
   `down()` gövdesinin boş/no-op olmadığını doğrular (deterministik, false-positive riski
   düşük — introducedIn tarihi filtresi YOK, çünkü "boş down()" her zaman yanlıştır, geçmişte
   de yanlıştı; ama mevcut 137 dosyanın hiçbiri ihlal etmediği için sorun çıkarmaz).
2. **`StagedTableDropRule`** — `Severity::Warning`. introducedIn tarihinden sonraki
   migration'larda `Schema::dropIfExists`/`Schema::drop` çağrılan tablo adının, daha önceki
   bir migration'da `_deprecated_` sonekiyle rename edildiği (karantinaya alındığı)
   doğrulanır; değilse "önce rename edin" önerisiyle Finding üretir.
3. **`SoftDeleteShouldBePrunableRule`** — `Severity::Warning`. `SoftDeletes` kullanıp
   `Prunable` kullanmayan modelleri, sınıf adı deseniyle (`*Movement|*History|*Log|
   *TimelineEntry|*Run`) eşleşiyorsa işaretler. Mevcut bilinçli istisnalar (`Tenant`,
   `Carrier`, `Product`, `Warehouse`, `Category`, `BankAccount`, `SupplierInvoice`,
   `DesignCard`, `ProductionOrder`, `Material`, `User`) rule içinde küçük bir
   `EXCLUDED_MODELS` sabitiyle günden itibaren susturulur (SchemaAuditCommand'ın
   `WHITELIST` desenine benzer).
4. **`MigrationNotEditedAfterMergeRule`** — `Severity::Warning`. introducedIn'den sonra
   değiştirilen migration dosyaları için `git log --follow -- <dosya>` çalıştırıp commit
   sayısı 1'den fazlaysa (yani dosya ilk eklendikten sonra tekrar değiştirilmiş) işaretler.
   Fuzzy bir sinyal olduğu için Warning ve muhtemelen kalıcı olarak Experimental kalır
   (bu repo'da migration'lar tek-commit disiplinine sahip değil — agent bunu doğruladı).

**Dosyalar**: `tools/architecture-doctor/app/Rules/Migrations/{MigrationDownIsRealRule,
StagedTableDropRule,SoftDeleteShouldBePrunableRule,MigrationNotEditedAfterMergeRule}.php`.
**Test**: her kural için fixture migration/model dosyaları içeren bir Unit test (gerçek
`Modules/*/database/migrations` taranmaz, izole fixture path'leri kullanılır) +
`ArchitectureDoctorCommandTest`'e 4 yeni id'nin rapora düştüğünü doğrulayan bir satır.
**Verification**: `php artisan architecture:doctor` çıktısında 4 yeni rule id görünür,
hepsi PASSED (mevcut kod tabanı zaten temiz); `--ci` exit 0 kalır (hepsi Experimental).

---

## Faz 3 — AI Layer

**Bulgular**: Creative (11 contract, hepsi Gemini+Mock çifti tam) ve Atelier
(`ConceptImageGeneratorContract` + Gemini/Mock) düzgün; **Product bozuk** —
`ProductDescriptionGenerator` contract'ı Mock'suz, config-switch'siz, doğrudan Gemini'ye
bağlı (`ProductServiceProvider.php:28`). Dizin yapısı üç modülde de FARKLI (Creative:
`Services/Ai/{Contracts,Drivers/{Gemini,Mock,Fal}}`; Atelier: `Services/Concept/
{Contracts,Drivers}` düz; Product: `Services/Ai/Contracts` + sürücüler `Services/Ai/`
içinde gevşek, `Drivers/` klasörü hiç yok) — ama **isim öneki tutarlı**: her yerde
`Gemini*`/`Fal*`/`Mock*`. Kurallar bu yüzden dizin yerine isim deseni kullanıyor.

### Kurallar

1. **`AiConcreteDriverBypassRule`** — `Severity::Critical`. `new (Gemini|Fal|Mock)
   [A-Za-z]+\(` veya bir constructor'da `(Gemini|Fal|Mock)[A-Za-z]+ \$` tip ipucu arayan,
   ama bunu SADECE ilgili sürücünün kendi ServiceProvider'ı VEYA kendi tanım dosyası
   DIŞINDA arayan bir kural. Mevcut kod tabanında 0 ihlal bulundu (agent doğruladı) — yani
   bu kural gönderildiği gün zaten yeşil, kısa sürede Stable'a yükseltilebilir.
2. **`AiMockDriverPresentRule`** — `Severity::Warning`. Her `*ServiceProvider.php` içindeki
   `$this->app->bind(XContract::class, ...)` çağrılarını tarar; bağlanan hedef (veya closure
   içindeki dallardan biri) `Mock*` desenine uymuyorsa Finding üretir. **Bugün Product için
   1 gerçek ihlal üretecek** — bu bilinçli, ilk günden faydalı bir sinyal (baseline'a
   alınmıyor, çünkü tek ve bilinen bir örnek; direkt görünür kalması istendi).

**Not**: Product'ın Creative/Atelier'den farklı dizin yapısını tek bir yapıya zorlamak bu
fazın kapsamı DEĞİL — bu bir refactor önerisi, mimari kural değil (dizin uyumu zorlanırsa
mevcut, çalışan kod gereksiz yere kırılır).

**Dosyalar**: `tools/architecture-doctor/app/Rules/AiLayer/{AiConcreteDriverBypassRule,
AiMockDriverPresentRule}.php`.
**Test**: `AiMockDriverPresentRule`'ın gerçek `ProductServiceProvider.php`'yi tespit ettiğini
doğrulayan bir entegrasyon testi (fixture değil, gerçek dosya — çünkü gerçek bir bulgu var).
**Verification**: `php artisan architecture:doctor` çıktısında `ai-mock-driver.present`
FAILED görünür (Product için), `ai-driver.bypass` PASSED görünür; `--ci` exit 0 (ikisi de
Experimental).

---

## Faz 4 — Controller Discipline

**Bulgular ve kapsam daraltması**: `laravel-controller` skill'i çoklu-tablo `DB::transaction`
kullanımını VE tekli Eloquent create/update'i controller içinde açıkça meşru sayıyor
(`ProductController::store` referans örnek olarak gösteriliyor). doctor.md'nin bu iki
maddesi bu yüzden **düşürüldü** — mevcut, onaylı konvansiyonla çelişirdi. `DB::table(`
controller'larda 0 kez kullanılmış (deterministik, temiz bir kural için iyi taban). En
büyük 5 controller: `SettingsController` (620 satır), `TryonController` (379),
`ProductController` (368), `CreativeStudioController` (337), `PatternController` (298).

### Kurallar

1. **`ControllerBypassesDbTableRule`** — `Severity::Critical`. Controller dosyalarında
   `DB::table(` çağrısı arar (skill'in "controller iş kuralı taşımaz, Service'e delege
   eder" ilkesinin en net, en az tartışmalı ihlali). 0 mevcut ihlal — hızlı Stable
   adayı.
2. **`LargeControllerRule`** — `Severity::Warning`, kalıcı olarak rapor-only (bu bir
   metrik, kesinlik değil). Eşik: 250 satır (mevcut dağılıma göre — 5 controller şu an
   bunun üzerinde, bunlar ilk günden görünür "keşfedilmiş borç" olur, baseline'a alınmaz,
   çünkü zaten bilinen ve kabul edilen bir metrik uyarısı, blocking olmayacağı için zarar
   vermez).

**Dosyalar**: `tools/architecture-doctor/app/Rules/Controllers/{ControllerBypassesDbTableRule,
LargeControllerRule}.php`.
**Test**: fixture bir controller dosyasıyla her iki kuralın da tetiklendiğini doğrulayan
Unit testler.
**Verification**: rapor `large-controller` için 5 Finding gösterir (isim + satır sayısı),
`db-table-bypass` PASSED; `--ci` exit 0.

---

## Faz 5 — Tenant Isolation

**Bulgular**: `BelongsToTenant` trait'i (`app/Models/Traits/BelongsToTenant.php`) hiçbir
modelde kullanılmıyor — ölü kod, otomatik/global tenant scope YOK. Gerçek koruma tamamen
manuel: katalog modelleri için `scopeAccessibleToTenant`/`accessibleToTenant()`
(`Modules/Product/Models/Product.php:196`), sahiplik modelleri için Policy'de `tenant_id`
karşılaştırması (`Modules/Tenant/Policies/OrderPolicy.php`, `PortalOrderPolicyTest`'in test
ettiği desen). Bare `::all()` şu an Product/Order/ProductVariant/Category/Brand üzerinde 0
kez bulundu — kural ileriye dönük bir regresyon muhafızı (tıpkı `RouteAuthorizationGateTest`
gibi, ama route yerine model sorgusu seviyesinde).

### Kural

1. **`TenantScopedQueryRule`** — `Severity::Critical` (cross-tenant veri sızıntısı gerçek bir
   güvenlik riski, bu yüzden diğer birçok kuraldan farklı olarak Stable'a yükseltildiğinde
   gerçekten bloklaması hedefleniyor). `Modules/{Product,Tenant,Atelier,Finance}` altındaki
   Controller/Service dosyalarında `(Product|Order|ProductVariant|Category|Brand)::all\(\)`
   veya zincirsiz `::query()->get()` arar; aynı dosyada/metodda `accessibleToTenant(` veya
   `tenant_id` karşılaştırması yoksa Finding üretir. Model listesi kural içinde açık bir
   sabit dizi (module.json'a taşınması ileride düşünülebilir ama bugün gerek yok).

**Dosya**: `tools/architecture-doctor/app/Rules/TenantIsolation/TenantScopedQueryRule.php`.
**Test**: fixture bir controller/service dosyasıyla hem "korumalı" (accessibleToTenant
çağrılı) hem "korumasız" (bare ::all()) örneklerin doğru ayırt edildiğini doğrulayan Unit
testler.
**Verification**: rapor PASSED gösterir (mevcut kod tabanı temiz); regresyon kontrolü için
geçici olarak bir dosyaya bare `Product::all()` eklenip kuralın FAILED ürettiği, sonra geri
alındığı doğrulanır (Faz 1'deki deptrac doğrulamasıyla aynı yöntem).

---

## Faz 6 — Read-only Quality Rules

Hiçbiri asla bloklamaz (doctor.md'nin kendi ifadesiyle) — bu kategori kalıcı olarak
Experimental/rapor-only kalacak şekilde tasarlanıyor, hiçbir zaman Stable'a yükseltilmiyor.
İlk değerlendirmemde de belirttiğim gibi, "unused class/dead controller" gibi proje-geneli
erişilebilirlik analizini sıfırdan yazmak yerine olgun bir araç sarmalanıyor — tıpkı
`ModuleBoundaryRule`'un deptrac'ı sarmalaması gibi.

### Kurallar

1. **`PhpmdQualityRule`** — `phpmd/phpmd` (`composer require --dev`) `codesize` +
   `unusedcode` ruleset'leriyle çalıştırılır (`Process` ile, JSON/XML çıktısı parse edilip
   `Finding`'e çevrilir — `ModuleBoundaryRule` ile aynı desen). Long method, god
   class/large class, yüksek cyclomatic complexity, kullanılmayan private method/parametre
   burayı kapsar.
2. **`UnusedPublicServiceHeuristicRule`** — `Modules/*/Services/*.php` altındaki her public
   sınıf için kısa adının dosyanın dışında kaç kez geçtiğini sayan basit bir referans-sayımı
   (grep tabanlı). 0-1 geçiş → "kullanılmıyor olabilir" Finding'i. Açıkça heuristik ve
   yanlış-pozitif üretebileceği belgeleniyor (string bazlı `app()->make()`, config-driven
   binding, route-model binding gibi dinamik çözümlemeleri kaçırır) — sadece rapor,
   asla blocking değil, asla Stable'a yükselmiyor.

**Dosyalar**: `tools/architecture-doctor/app/Rules/Quality/{PhpmdQualityRule,
UnusedPublicServiceHeuristicRule}.php`.
**Test**: `PhpmdQualityRule`'ın gerçek phpmd'yi çalıştırıp en az bir bilinen büyük dosyada
(`SettingsController`, 620 satır) bulgu ürettiğini doğrulayan entegrasyon testi;
`UnusedPublicServiceHeuristicRule` için fixture tabanlı Unit test.
**Verification**: rapor iki yeni kategori altında (muhtemelen) çok sayıda Finding gösterir
— bu beklenen, çünkü ilk kez çalıştırılıyor; `--ci` etkilenmez (ikisi de kalıcı Experimental).

---

## Faz Sırası ve Uygulama Notu

Faz 2 → 3 → 4 → 5 → 6 sırasıyla, her biri ayrı bir uygulama turunda (Faz 0/1'de olduğu
gibi) hayata geçirilecek — bu plan hepsini tasarlıyor ama hiçbirini şimdi yazmıyor. Her
fazın uygulanması sırasında (Faz 1'deki deptrac regex/App-layer hataları gibi) gerçek
çalıştırma sırasında ortaya çıkabilecek küçük düzeltmeler beklenmeli; bu plan yaklaşımı
sabitliyor, son regex/eşiği değil.
