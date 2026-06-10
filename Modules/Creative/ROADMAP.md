# Creative Modülü — Genişletme Yol Haritası (Devam Dosyası)

> Bu dosya "kaldığı yerden devam" içindir. Yeni bir oturumda "Creative roadmap'e devam et"
> / "Faz 3'ü yap" dendiğinde buradan başlanır. Tam roadmap'in bağlamı:
> `~/.claude/plans/imdi-creative-mod-l-ndeki-zelliklieri-humble-cook.md` ve auto-memory
> `project_creative_brandcreative_roadmap`.

## Kararlar (sabit)
- Ayrı `BrandCreative` modülü YOK → mevcut `Modules/Creative` genişletiliyor.
- Render motoru **Python** kalıyor (resvg + Pillow). Node/Puppeteer kullanılmaz.
- AI sağlayıcı **Gemini + fal.ai idm-vton** (Replicate/ComfyUI değil). Anahtar yoksa mock'a düşer.

## Durum
- ✅ **Faz 1 — Brand Kit** (tamamlandı, doğrulandı)
- ✅ **Faz 2 — AI Sahne Pipeline** (tamamlandı, doğrulandı)
- ✅ **Faz 3 — Caption Servisi** (tamamlandı, doğrulandı)
- ⬜ **Faz 4 — UI: Brand Kit + Şablon editörü** ← SIRADAKİ
- ⬜ **Faz 5 — Olgunlaştırma + Dağıtım**

---

## ✅ Faz 1 — Brand Kit (yapıldı)
Tek doğruluk kaynağı marka token sistemi. Anahtar dosyalar:
- `database/migrations/2026_06_10_100000_create_brand_kits_table.php`, `Models/BrandKit.php`
- `Services/BrandTokenService.php` — `tokens()` (cache key `creative.brand_tokens.default`, 1h),
  `forget()` ile invalidate; çıktı `{palette, fonts, spacing, logos}`. Config fallback `creative.brand.defaults`.
- Render köprüsü: `RendererContract`/`PythonRenderer`/`CreativeRenderService` `array $brand` parametresi.
- `python/render.py` — `resolve_color_token()`: slot fill `token:<name>` → palette renginden çözülür.
- `database/seeders/BrandKitSeeder.php` → ana `DatabaseSeeder`'a bağlı.

## ✅ Faz 2 — AI Sahne Pipeline (yapıldı)
`Services/Ai/` altında compose(Gemini)→try-on(fal idm-vton) pipeline + mock fallback.
- Contract: `Services/Ai/Contracts/{SceneComposerContract,GarmentTryOnContract}.php`; DTO `SceneRequest.php`.
- Orchestrator: `Services/Ai/AiSceneService.php` (çıktı `ai-scenes/{productId}/`, `meta.ai_scene` izi).
- Sürücüler: `Drivers/Gemini/*`, `Drivers/Fal/*`, `Drivers/Mock/*`; yardımcı `Support/ImageFile.php`.
- Binding: `Providers/CreativeServiceProvider.php` (anahtar varsa gerçek, yoksa mock).
- Tetik: `creative_assets.meta.use_ai` (Studio.vue toggle → `generate` POST `use_ai`).
- Config: `config/config.php` → `creative.ai.*`.

---

## ✅ Faz 3 — Caption Servisi (yapıldı)
Üretilen görsele marka tonunda caption + hashtag üretimi. **Karar:** caption HER ZAMAN üretilir
(ayrı toggle yok); üretimi başarısız olursa render düşmez (non-fatal, `Log::warning`).

- `Drivers/Gemini/GeminiClient.php` → `generateText()`: aynı `generateContent` endpoint, `responseModalities`
  GÖNDERMEZ; `candidates[0].content.parts[].text` birleştirir. Model: `creative.ai.gemini.text_model`
  (`GEMINI_TEXT_MODEL`, varsayılan `gemini-2.5-flash`).
- Sözleşme/DTO/sürücüler (AI sahne deseniyle paralel): `Services/Ai/Contracts/CaptionGeneratorContract.php`,
  `Services/Ai/CaptionRequest.php`, `Drivers/Gemini/GeminiCaptionGenerator.php` (JSON parse + fallback),
  `Drivers/Mock/MockCaptionGenerator.php` (şablon). Ortak: `Support/HashtagHelper.php` (normalize/extract).
- Orchestrator: `Services/CaptionService.php` — `BrandTokenService` palet tonu + ürün (ad, kategori,
  materyal, cinsiyet) → `forProduct(Product)` → `{caption, hashtags[]}`.
- Binding: `Providers/CreativeServiceProvider.php` (`caption_driver=gemini` + anahtar → Gemini, yoksa mock).
  Config: `creative.ai.caption_driver` (`CREATIVE_CAPTION_DRIVER`).
- Bağlama: `CreativeRenderService::generate` render sonrası `buildCaption()` → `meta.caption`/`meta.hashtags`.
- UI: `CreativeStudioController::gallery` payload'a caption/hashtags; `updateCaption()` + route
  `creative.assets.caption.update` (PUT). `CreativeGallery.vue` düzenlenebilir textarea + hashtag input + kaydet.

## ⬜ Faz 4 — UI: Brand Kit + Şablon Editörü (SIRADAKİ)
**Amaç:** Operatörün SVG/JSON elle yazmadan brand kit ve slot yönetmesi.

**Yapılacaklar:**
1. **Brand Kit CRUD** — yeni `Http/Controllers/BrandKitController.php` + route'lar (`creative.brandkits.*`),
   `BrandKitEditor.vue` (palette/typography/logo/spacing düzenleme). Kayıtta `BrandTokenService::forget()`.
2. **Şablon Tasarımcısı** — `TemplateBuilder.vue`: yüklenen SVG üstüne sürükle-bırak slot konumlandırma,
   metin/görsel slot tipi, `fit` (cover/contain). Mevcut `CreativeTemplateController::update` zaten
   `slots` JSON güncelliyor — onun üstüne kur. Canlı önizleme: `python/inspect_template.py` + `render.py`.
3. Studio menüsüne editör sekmeleri.

**Kritik dosyalar:** `Resources/assets/js/Pages/` (yeni Vue), `Http/Controllers/CreativeTemplateController.php`,
yeni `BrandKitController`.

## ⬜ Faz 5 — Olgunlaştırma + Dağıtım
**Yapılacaklar:**
1. **Job retry:** `Jobs/GenerateCreativeJob.php` `tries=1` → `tries`+`backoff`; kalıcı (geçersiz şablon)
   vs geçici (AI/render timeout) hatayı ayır. AI uzun → Horizon/Redis önerilir.
2. **Analytics:** üretim sayısı/başarı oranı/ortalama süre/şablon performansı; Gallery üst barı.
3. **Asset versiyonlama** + **template A/B varyant** (talep gelirse). Versiyonlama gelirse AI çıktısı için
   `meta` yerine ayrı tablo düşün (Faz 2'de bilerek ertelendi).
4. **Export:** önce ZIP toplu indirme (harici API yok), sonra sosyal yayın (Instagram/Facebook Graph,
   TikTok) — yalnız `review_status=approved`. Caption Faz 3'ten gelir.

**CLAUDE.md uyumu:** Yeni tablo eklersen migration disiplini (gerçek `down()`, ileri tarihli),
sonra `php artisan schema:audit` ile şişme kontrolü.

---

## Devam etme talimatı (kendime not)
1. Bu dosyadan sıradaki ⬜ fazı seç.
2. `TaskCreate` ile o fazın adımlarını çıkar, `in_progress` işaretle.
3. Faz bitince: bu dosyada ⬜ → ✅ yap + kısa "yapıldı" özeti ekle, auto-memory
   `project_creative_brandcreative_roadmap` dosyasını güncelle.
4. Doğrulama: mock yol + tinker/araç ile uçtan uca; gerekiyorsa `schema:audit`.
