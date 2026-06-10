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
- ✅ **Faz 4 — UI: Brand Kit + Şablon editörü** (tamamlandı, doğrulandı)
- ✅ **Faz 5 — Olgunlaştırma + Dağıtım** (çekirdek tamam; A-B varyant + sosyal yayın ertelendi)

> 🎉 5 fazlık BrandCreative roadmap'i tamamlandı. Kalan işler talep-bazlı (aşağıda "ertelendi").

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

## ✅ Faz 4 — UI: Brand Kit + Şablon Editörü (yapıldı)
Operatörün SVG/JSON elle yazmadan brand kit ve slot yönetmesi.

**ÖNEMLİ MİMARİ BULGU:** Slotlar SVG'de `data-slot` attribute'larında yaşar; `render.py`/`inspect_template.py`
slotları **SVG'den** okur (DB `creative_templates.slots` yalnızca inceleme kopyasıdır — tek başına render'ı
ETKİLEMEZ). Bu yüzden tasarımcı değişiklikleri SVG'ye geri yazılmalıdır.

- **Slot yazma:** yeni `python/apply_slots.py` — slot listesini SVG XML'ine işler (mevcut `data-slot`
  elemanlarını günceller, `ref=null` olanları yeni `<text>`/`<rect>` olarak ekler, payload'da olmayanları siler).
  `RendererContract::applySlots()` + `PythonRenderer::applySlots()` (config `creative.render.scripts.apply`).
  `CreativeRenderService::applyTemplateSlots()` = applySlots + inspect (DB'yi tazeler).
- **Brand Kit CRUD:** `Http/Controllers/BrandKitController.php` (index/store/update/destroy + `uploadLogo` JSON),
  `Http/Requests/StoreBrandKitRequest.php`, route'lar `creative.brandkits.*`. Tekil default senkronu
  (`syncDefault`), her yazımda `BrandTokenService::forget()`. UI: `Pages/CreativeBrandKits.vue`
  (palet color-picker + hex, spacing, logo path+upload, varsayılan toggle).
  **Tipografi yüklemeli (güncelleme):** `uploadFont` route `creative.brandkits.font` — tekli font
  (.ttf/.otf/.woff/.woff2) **veya** .zip (`extractFontsFromZip` içindeki fontları `brand_kits/fonts/`'a açar).
  Birden fazla font (kütüphane `typography.fonts:[{name,path}]`); regular/bold kütüphaneden `<select>` ile
  seçilir. Yüklenen göreli yol `BrandTokenService::resolvePath` ile mutlak yola çözülüp render'a verilir.
- **Şablon Tasarımcısı:** `Pages/CreativeTemplates.vue` — SVG üstünde sürükle-bırak slot kutuları (move +
  resize handle), metin/görsel slot ekle-sil, özellik paneli (x/y/w/h, fit, font_size/bold/align/fill).
  Kaydet → `creative.templates.slots` (PUT) → `applyTemplateSlots`. SVG yükle/yeniden adlandır/aktif/sil.
  `CreativeTemplateController::index()` (tüm şablonlar + svg_url) + `updateSlots()`.
- **Navigasyon:** `Components/CreativeNav.vue` sekme çubuğu (Stüdyo/Galeri/Şablonlar/Marka Kiti); 4 sayfaya eklendi.
- Doğrulandı: apply_slots→inspect zinciri (token fill korunur), brand default senkronu+cache, `npm run build` temiz.

## ✅ Faz 5 — Olgunlaştırma + Dağıtım (çekirdek yapıldı)
1. **Job retry (yapıldı):** `Jobs/GenerateCreativeJob.php` → `tries=3` + `backoff()` [10s,30s].
   Kalıcı vs geçici hata ayrımı: `Services/Exceptions/PermanentRenderException.php` (şablon/ürün yok →
   retry YOK); geçici hata son denemeye kadar rethrow ile retry. `CreativeRenderService` artık şablon/ürün
   yoksa `PermanentRenderException` fırlatır. **Not:** AI uzun sürdüğü için prod'da Horizon/Redis önerilir
   (kod değişikliği değil, altyapı; QUEUE_CONNECTION=redis).
2. **Analytics (yapıldı):** `CreativeStudioController::stats()` (Postgres `FILTER` + `meta->>'render_ms'`
   cast) → toplam/hazır/işlemde/başarısız/onaylı, başarı oranı, ort. süre, şablon performansı (top 5).
   `render_ms` artık `CreativeRenderService::generate` içinde meta'ya yazılır. UI: `CreativeGallery.vue`
   üst barı + şablon bar grafikleri.
3. **Asset versiyonlama + A-B varyant: ERTELENDİ** (roadmap'te "talep gelirse"). Gelirse AI çıktısı için
   `meta` yerine ayrı tablo + CLAUDE.md migration disiplini (gerçek `down()`, ileri tarihli, sonra `schema:audit`).
4. **Export (ZIP yapıldı):** `CreativeStudioController::export()` → `ZipArchive` ile `review_status=approved`
   + `status=done` görselleri; caption/hashtag varsa yanına `.txt`. Route `creative.export` (GET), Gallery
   header'da "ZIP indir (N)" linki (`<a href>`, Inertia değil — dosya indirme). Harici API yok.
   **Sosyal yayın (Instagram/Facebook Graph, TikTok): ERTELENDİ** — gerçek OAuth/uygulama kimlik bilgisi
   ve onay süreci gerektirir; ZIP export şimdilik dağıtım yolu.

**CLAUDE.md uyumu:** Faz 5'te yeni tablo EKLENMEDİ (analytics anlık hesap, `render_ms` mevcut meta JSON'unda,
export dosya sistemi) → migration/schema:audit gerekmedi. Doğrulandı: analytics SQL Postgres'te çalışır,
export route + ZIP mantığı, retry/backoff, `npm run build` temiz.

---

## Devam etme talimatı (kendime not)
1. Bu dosyadan sıradaki ⬜ fazı seç.
2. `TaskCreate` ile o fazın adımlarını çıkar, `in_progress` işaretle.
3. Faz bitince: bu dosyada ⬜ → ✅ yap + kısa "yapıldı" özeti ekle, auto-memory
   `project_creative_brandcreative_roadmap` dosyasını güncelle.
4. Doğrulama: mock yol + tinker/araç ile uçtan uca; gerekiyorsa `schema:audit`.
