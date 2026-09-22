# Creative — AI Pazarlama Görseli (Rules Engine) — Tasarım + Uygulama Kaydı

- **Tarih:** 2026-09-22
- **Kapsam:** `Modules/Creative` — pazarlama görseli üretim hattı (`CreativeAsset`/`CreativeTemplate`, `CreativeRenderService`, Creative Stüdyo).
- **Durum:** **Uygulandı ve commit edildi** (3 commit: `b94e128`, `edda355`, `11fa397`). Bu doküman, `2026-09-22-modul-calisma-mantigi-revizyonu-design.md` §5'in açık sorularını kapatan ayrı tasarım oturumunun çıktısı — **kod yazıldıktan sonra**, gerçek akışın tamamı görüldükten sonra yazıldı (bilinçli sıralama: önce uygulama, sonra doküman, çünkü tasarım kararlarının çoğu kod okunurken netleşti).

## Bağlam

`2026-09-22-modul-calisma-mantigi-revizyonu-design.md` §5'te iki açık soru bırakılmıştı:
1. Mevcut `TemplateGeneratorService`/`LayoutConstraintEngine`/`BrandTokenService`/`CreativeCopyRuleEngine`'den ne kadarı yeniden kullanılabilir?
2. "Marka bütünlüğü için gerekli adımlar" somut olarak ne — bir kural motoru mu, prompt-mühendisliği mi?

Kullanıcı bu oturumda kapsamı genişletti: markanın kendi paylaşımının (`brand_post`) yanına, **iş ortaklarının kendi hesabında paylaşacağı görsel** (`partner_share`) ve **kampanya görseli** (`campaign`) kanalları eklendi. Kritik mimari karar kullanıcıdan geldi:

> AI'ın her şeyi üretmesine izin verme — logo/fiyat/telefon/web adresi/kampanya tarihi/ürün adı/SKU/iş ortağı bilgileri gibi kesin metinleri AI'a çizdirmek yerine frontend/rendering katmanın oluştursun. AI: fotoğraf+atmosfer+arka plan+kompozisyon+dekoratif görsel üretsin.

Üç entegrasyon yaklaşımı arasından kullanıcı **"Mevcut 'svg' yolunu genişlet"**i seçti — `ai_compose` (Faz J, fal.ai Flux tam-post) dokunulmadan ayrı kaldı, yeni bir paralel sistem kurulmadı.

## Mimari

```
BrandKit ──┐
           ├─→ CreativeRulesEngine.buildSpec() ─→ CreativeSpec ─┬─→ GeminiPromptBuilder (AI sahne — atmosfer/kompozisyon/dekor)
PartnerBrandKit (partner_share'de) ─┘                            │
                                                                   └─→ (spec'ten BAĞIMSIZ) SVG şablon render adımı
                                                                        (logo/partner_logo/headline/sub_headline/cta_button —
                                                                        kesin font/renk pipeline'ı, hiç AI değil)
```

**Neden iki ayrı yol:** `CreativeSpec` yalnızca Gemini'ye giden prompt'u etkiler — AI'nin ÜRETTİĞİ görüntüye (arka plan/atmosfer/dekor) kısıtlar koyar, hiçbir zaman kesin metin/logo çizdirmez. Kesin unsurlar (marka logosu, partner logosu, headline/CTA metni) her zaman `CreativeRenderService::generate()`'in SVG render adımında — `PythonRenderer`/`render.py`'nin `data-slot="..."` eşlemesiyle — ayrıca yerleştirilir. Bu iki yol birbirinden bağımsız çalışır; `CreativeSpec` sadece "bu alanı boş/temiz bırak" der, o alanı asıl dolduran render adımıdır.

### Kanallar

| Kanal | `logoRequired` | `partnerLogoRequired` | `background` | `maxDecorativeElements` |
|---|---|---|---|---|
| `brand_post` | true | false | `brand_compatible` | 2 |
| `partner_share` | true (marka logosu KALKMAZ) | true (partner varsa) | `brand_compatible` | 1 (iletişim/fiyat bloğuna yer açmak için kısıtlı) |
| `campaign` | true | false | `campaign_themed` | 3 (rozet/countdown gibi vurgulara daha açık) |

Kural: `CreativeRulesEngine::buildSpec()` (`Modules/Creative/Services/CreativeRulesEngine.php`) — saf, DB'siz, AI çağrısız bir fonksiyon (aynı disiplin: `GarmentIdentityRuleEngine`).

## Veri akışı (uçtan uca, gerçekte çalışan hali)

1. **Creative Stüdyo** (`CreativeStudio.vue`) — "AI sahne / giydirme" açıkken "Kanal" ve (partner_share'de) "İş Ortağı" seçici görünür. `partner_share` seçiliyse profil seçilmeden "Görselleri Üret" pasif kalır (client-side guard).
2. `POST /creative/generate` → `GenerateCreativesRequest` (`channel`/`partner_brand_kit_id` doğrulanır, ikisi de `nullable` — `useAi` kapalıyken form `null` gönderir) → `CreativeStudioController::generate()` bunları `CreativeAsset.meta`'ya yazar.
3. `GenerateCreativeJob` → `CreativeRenderService::generate()`:
   - `meta.channel`/`meta.partner_brand_kit_id`'den kanal + `PartnerBrandKit` **bir kez** çözülür (hem spec hem copy adımına aynen aktarılır, tekrar sorgu atılmaz).
   - Partner logosu varsa `imagePaths['partner_logo']`'ya enjekte edilir (`Media::localPath()` ile `config('creative.disk')`'ten çözülür — `BrandTokenService::resolvePaths()` ile aynı desen).
   - `wantsAi()` dalındaysa `CreativeRulesEngine::buildSpec()` → `CreativeSpec` → `SceneRequest::$spec` → `GeminiPromptBuilder::specLines()` (metin/logo/fiyat/CTA çizmeyi **açıkça yasaklayan** satırlar + logo/partner-logo için "temiz alan bırak" yönergesi + dekoratif öğe sınırı + banned_words).
   - `buildCopy()`: `partner_share` + `PartnerBrandKit.cta_phrases` tanımlıysa, CTA kapalı-listesi (hem AI hint'i hem `CreativeCopyRuleEngine`'in coerce denetimi) marka kitinin genel listesinin **yerine** partner'ın listesini kullanır.
   - `PythonRenderer::render()` marka logosunu (`brand.logos.primary`) otomatik `imagePaths['logo']`'ya enjekte eder (önceden var olan davranış) — partner logosu artık yukarıda ayrıca enjekte edildiği için şablon `partner_logo` slotu tanımlıyorsa o da otomatik yerleşir.
   - `render.py`'nin `composite_images()`/`draw_texts()` fonksiyonları tamamen **slot-key jenerik** — `imagePaths`/`values` içindeki herhangi bir key, SVG'de aynı `data-slot="<key>"`'e sahip elemanla otomatik eşleşir. Yani `partner_logo` için render.py'de hiçbir kod değişikliği gerekmedi; tek gereken PHP tarafında doğru key'e path koymaktı.

## Şablon yazarı için pratik sonuç (kod dışı, manuel adım)

`partner_share` kanalının partner logosunu göstermesi için, kullanılacak `CreativeTemplate`'in SVG'sinde `data-slot="partner_logo"` taşıyan bir `<rect>`/`<image>` elemanı **tanımlı olmalı** (marka logosu için `data-slot="logo"` zaten aynı desende). Tanımlı değilse: `imagePaths['partner_logo']` sessizce göz ardı edilir (slot yoksa `composite_images()` hiçbir şey yapmaz) — hata vermez, ama partner logosu görselde **çıkmaz**. Yeni slot eklendikten sonra `CreativeTemplates.vue`'daki mevcut "yeniden incele" (`inspectTemplate()`) akışı slotu DB'ye yansıtır; kod değişikliği gerekmez.

## Bilinçli kapsam dışı bırakılan: fiyat gösterimi

`PartnerBrandKit.price_display` (`enabled`/`note`) DB'de tutuluyor ve admin UI'sında düzenlenebiliyor, ama **render'a hiç bağlanmadı**. Sebep: template sisteminde (BrandKit için de) hiçbir yerde bir "price" slot kavramı yok — sadece `headline`/`sub_headline`/`cta_button` biliniyor. Bunu bağlamak yeni bir slot tipi + fiyatın nereden geleceği (`Product.price`? tenant'a özel liste fiyatı?) + formatlama kararı gerektiriyor — ayrı bir tasarım kararı, bu oturumun kapsamına alınmadı. `price_display.enabled=false` iken zaten hiçbir şey beklenmiyor; `true` olduğunda şu an tek etkisi yok (sessiz no-op) — bu bilinçli bir sınırlama, gizli bir bug değil.

## Dosyalar

| Dosya | Rol |
|---|---|
| `Modules/Creative/Services/Ai/Support/CreativeSpec.php` | Rules Engine çıktısı — value object. `Services/Ai/Support/` altında (deptrac AiInfra katmanı — `*Request.php` value object'leriyle aynı sözleşim yüzeyi; `deptrac.php`'nin `$aiInfraPattern`'i). |
| `Modules/Creative/Services/CreativeRulesEngine.php` | Saf kanal→spec kural katmanı. |
| `Modules/Creative/Services/Ai/SceneRequest.php` | `?CreativeSpec $spec` alanı eklendi. |
| `Modules/Creative/Services/Ai/Drivers/Gemini/GeminiPromptBuilder.php` | `specLines()` — spec varsa kanal/format'a özel AI yönergeleri. |
| `Modules/Creative/Services/CreativeRenderService.php` | Kanal/partner çözümü, partner logosu enjeksiyonu, partner CTA override, `buildCreativeSpec()`. |
| `Modules/Creative/Models/PartnerBrandKit.php` + migration | `partner_share` kanalı için BrandKit'e ek profil (logo/iletişim/CTA/fiyat gösterimi — fiyat şu an render'a bağlı değil, yukarı bkz.). |
| `Modules/Creative/Http/Controllers/PartnerBrandKitController.php` + `StorePartnerBrandKitRequest.php` | CRUD + logo upload (BrandKitController ile aynı desen). |
| `Modules/Creative/Resources/assets/js/Pages/PartnerBrandKits.vue` | Yönetim ekranı (`CreativeNav`'a "İş Ortağı Profilleri" sekmesi eklendi). |
| `Modules/Creative/Http/Requests/GenerateCreativesRequest.php` + `CreativeStudioController.php` | `channel`/`partner_brand_kit_id` doğrulama + meta yazımı. |
| `Modules/Creative/Resources/assets/js/Pages/CreativeStudio.vue` | Kanal + iş ortağı seçici (yalnız `useAi` açıkken). |
| `Modules/Creative/database/seeders/CreativePermissionSeeder.php` | `creative.partnerbrandkit.manage` izni. |

## Doğrulama (bu oturumda yapıldı)

- `php -l` tüm yeni/değişen dosyalarda.
- `@vue/compiler-sfc` ile `PartnerBrandKits.vue`/`CreativeStudio.vue`/`CreativeNav.vue` statik derleme (bu repoda `npm run dev`/`build` yasak — `public/` canlı docroot).
- `php artisan route:list` — 5 yeni `partner-brandkits.*` route'u + `creative.studio` doğru render ediyor.
- `php artisan architecture:doctor` → `module-boundary.deptrac` PASSED (`CreativeSpec`'in `Support/`e taşınması gerçek bir ihlali düzeltti — ilk yazımda `GeminiPromptBuilder`/`SceneRequest` [AiInfra] `CreativeSpec`'e [Domain] bağımlıydı, deptrac bunu doğru şekilde yakaladı).
- Tinker: `app(CreativeRenderService::class)` DI container'dan hatasız çözülüyor (`CreativeRulesEngine` constructor'sız, autowire sorunsuz).
- Tinker: `channel: null, partner_brand_kit_id: null` kombinasyonunun `GenerateCreativesRequest` validasyonunu geçtiği doğrulandı (nullable eksikliği bir regresyon olarak yakalanıp düzeltildi).
- Superadmin rolünün `creative.partnerbrandkit.manage` iznine sahip olduğu tinker ile doğrulandı.

## Bilinen sınırlamalar / sıradaki adımlar

1. **Fiyat gösterimi render'a bağlı değil** (yukarı bkz.) — ayrı bir tasarım kararı gerektiriyor.
2. **`campaign` kanalının kendine özgü UI alanı yok** — şu an sadece `background=campaign_themed`+`maxDecorativeElements=3` farkı var, kampanya tarihi/rozet gibi kesin metinler için henüz bir slot/akış tanımlanmadı (kullanıcı ilk kararında "kampanya görseli, brand_post'a yakın" demişti — bu haliyle tutarlı, ama genişletilmedi).
3. **Hiçbir mevcut `CreativeTemplate`'de `data-slot="partner_logo"` yok** — bu, kod değil içerik/tasarım işi; ilk `partner_share` kullanımından önce en az bir şablonun güncellenmesi gerekiyor.
4. Tenant'ın SMS'li 2 aşamalı başvuru akışı hâlâ VatanSMS anlaşmasını bekliyor — bu Creative işiyle ilgisiz, ayrı bir gelecek oturum.
