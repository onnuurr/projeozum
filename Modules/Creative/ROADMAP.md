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

---

## 🆕 Özellik İzi — Sanal Manken Stüdyosu (yeni, BrandCreative roadmap'inden bağımsız)
**Amaç:** AI ile yeniden kullanılabilir sanal manken üret → her mankene 20+ poz üret (kimlik
referanslı) → ürünleri bu pozlara idm-vton ile giydir → sonucu `product_images`'a (ürün seviyesi) yaz.

**Kararlar (kullanıcı onaylı):** manken = AI ile sıfırdan; pozlar = AI ile (kimlik referanslı);
çıktı = product_images (ürün seviyesi); konum = Creative içinde yeni "Sanal Manken" sekmesi.
Mevcut Gemini compose + fal idm-vton + ImageFile + job/retry deseni yeniden kullanılır.

**Fazlar:** A=Şema+model+config · B=Manken üretimi · C=Poz üretimi · D=Ürün giydirme→product_images · E=Cila.

- ✅ **Faz A — Şema + Model + Config (yapıldı, migrate + schema:audit temiz)**
  - Migrationlar (ileri tarihli, gerçek `down()`): `2026_06_11_100000_create_creative_mannequins_table`,
    `..._100100_create_creative_mannequin_poses_table`, `..._100200_create_creative_tryon_results_table`.
  - Modeller: `Models/Mannequin.php` (softDeletes, status sabitleri, `poses()`/`results()`),
    `Models/MannequinPose.php` (status sabitleri, `mannequin()`/`results()`),
    `Models/TryonResult.php` (product/mannequin/pose/productImage ilişkileri; `product_id+pose_id` unique = idempotensi).
  - Config: `creative.mannequin.*` → `output_dir='mannequins'`, `min_poses=20`, **24'lük poz kataloğu** (`key/label/prompt`).
  - Not: `creative_tryon_results` izlenebilirlik/idempotensi içindir; nihai görsel yine `product_images`'a yazılır.
- ✅ **Faz B — Manken üretimi (yapıldı; mock uçtan uca + `npm run build` temiz)**
  - DTO/Contract: `Services/Ai/MannequinRequest.php` (promptOverride dahil), `Contracts/MannequinComposerContract.php`.
  - PromptBuilder: `Drivers/Gemini/MannequinPromptBuilder.php` — try-on'a uygun NÖTR tam boy, sade arka plan, taban kıyafet; tarif alanlarından kimlik cümlesi.
  - Sürücüler: `Drivers/Gemini/GeminiMannequinComposer.php`, `Drivers/Mock/MockMannequinComposer.php`. Binding: provider'da `MannequinComposerContract` (gemini anahtarı varsa gerçek, yoksa mock).
  - Orchestrator: `Services/MannequinService.php` — compose → `mannequins/{id}/reference.png` diske → model `prompt`/`reference_image_path`/`status=ready`. Geçici dosya finally ile temizlenir.
  - Job: `Jobs/GenerateMannequinJob.php` (tries=3, backoff [10,30], generating→ready/failed).
  - HTTP: `Http/Requests/StoreMannequinRequest.php`, `Http/Controllers/MannequinController.php` (index/store/regenerate/destroy). Route'lar `creative.mannequins.*`.
  - UI: `Components/CreativeNav.vue`'ya "Sanal Manken" sekmesi; `Pages/CreativeMannequins.vue` (üret formu + manken grid'i, status rozeti, üretim sürerken 5sn otomatik yenileme).
  - Not: pozlar Faz C'de; şu an grid "0/min poz hazır" gösterir.
- ✅ **Faz C — Poz üretimi (yapıldı; mock uçtan uca: 24 poz seed + üretim, `npm run build` temiz)**
  - DTO/Contract: `Services/Ai/MannequinPoseRequest.php`, `Contracts/MannequinPoseComposerContract.php`.
  - PromptBuilder: `Drivers/Gemini/MannequinPosePromptBuilder.php` — referans görseldeki KİMLİĞİ koru (yüz/ten/saç/vücut/taban kıyafet), yalnız duruşu değiştir; sade arka plan tutarlı.
  - Sürücüler: `Drivers/Gemini/GeminiMannequinPoseComposer.php` (referans görseli `refs` olarak inline verir), `Drivers/Mock/MockMannequinPoseComposer.php`. Binding: `MannequinPoseComposerContract`.
  - Orchestrator: `Services/MannequinPoseService.php` — `seed()` katalogdan 24 pozu `updateOrCreate` ile işler (idempotent, queued); `generate()` referansı `Storage::path` ile çözüp compose → `mannequins/{id}/poses/{key}.png` → pose `ready`.
  - Job: `Jobs/GeneratePoseJob.php` (poz başına granüler; tries=3, backoff [10,30]).
  - HTTP: `MannequinController` → `poses()` (detay), `generatePoses()` (seed+dispatch, manken `ready` şartı), `regeneratePose()`. Route'lar `creative.mannequins.poses`, `...poses.generate`, `creative.poses.regenerate`.
  - UI: `Pages/CreativeMannequinPoses.vue` (kimlik thumb + poz grid'i, status rozeti, tek poz yeniden üret, üretim sürerken 5sn auto-refresh); `CreativeMannequins.vue` kartına "Pozlar →" linki (yalnız ready manken).
- ✅ **Faz D — Ürün giydirme (yapıldı; mock uçtan uca + idempotensi + `npm run build` temiz)**
  - Orchestrator: `Services/ProductOnModelService.php` — `queue()` ürün+mankenin HAZIR pozları için `tryon_result` satırları (updateOrCreate, product_id+pose_id benzersiz); `generate()` pozu "model" + ürün kapağını "giysi" alıp `GarmentTryOnContract::tryOn` → çıktı `products/{id}/onmodel_*.png` (public disk) → `product_images`'a `images()->create` (is_cover=false). Yeniden üretimde eski ProductImage+dosya temizlenir.
  - **URL normalizasyonu (bulgu):** product_images.url bu projede MUTLAK (`http://host/storage/...`). `toStorageRelative()` ile garment HTTP indirmeden yerelden okunur; `deleteProductImage()` '/storage/' işaretçisiyle hem mutlak hem göreli url'i siler (eski `str_starts_with('/storage/')` mutlak url'de eşleşmiyordu).
  - Job: `Jobs/GenerateOnModelJob.php` (sonuç başına granüler; tries=3, backoff [10,30]; generating→done/failed).
  - HTTP: `Http/Requests/GenerateTryonRequest.php`, `Http/Controllers/TryonController.php` (index: ürünler + uygun mankenler[hazır+≥1 hazır poz] + son 60 sonuç; store: queue+dispatch). Route'lar `creative.tryon.*`.
  - UI: `Components/CreativeNav.vue`'ya "Ürün Giydirme" sekmesi; `Pages/CreativeTryon.vue` (ürün seç → manken seç → poz çoklu-seç → giydir; sonuç grid'i status rozeti + 5sn auto-refresh).
  - Doğrulandı (mock): manken+poz üret → giydir → product_images'a yazıldı (alt: "Ürün — Poz"); yeniden üretim toplam görseli artırmadı, eski dosya silindi, image_id değişti.
- ✅ **Faz E — Cila (yapıldı; aksiyonlar uçtan uca + `npm run build` temiz)**
  - Kapak yap: `TryonController::setCover()` — giydirme çıktısını ürün kapağı yapar (diğer kapakları düşürür). Route `creative.tryon.cover`. UI: sonuç kartında "Kapak yap" + ★ kapak rozeti.
  - Sonuç sil: `TryonController::destroyResult()` — ürün görselini (dosya + satır) ve tryon_result'ı siler. Route `creative.tryon.destroy`. `deleteProductImage()` mutlak/göreli url'i '/storage/' işaretçisiyle çözer.
  - Poz ayıklama: `MannequinController::destroyPose()` — pozu görseliyle siler (kimlik tutarsızsa kaldır). Route `creative.poses.destroy`. UI: poz kartında "Sil".
  - `TryonController::index` sonuçlarına `is_cover` eklendi (UI rozet/aksiyon için).
  - Doğrulandı: setCover (onmodel kapak=1, eski kapak=0), destroyResult (görsel+dosya+satır gitti), destroyPose (poz+dosya gitti).

**🎉 Sanal Manken Stüdyosu 5 fazı tamam.** Tam zincir: manken üret → 20+ poz üret → ürünü pozlara giydir → product_images'a ürün görseli + kapak yap.

### 🔄 REVİZYON (mimari pivot — kullanıcı talebi)
Üç yapısal değişiklik yapıldı; aşağıdaki C/D açıklamaları ESKİ tasarımı anlatır, güncel davranış bu revizyondur:
1. **Manken = yüz + vücut ölçüleri.** `creative_mannequins`'e `face, height_cm, bust_cm, waist_cm, hips_cm` eklendi (migration `2026_06_11_101000`). `MannequinPromptBuilder` yüz + ölçüleri prompt'a işler (`Facial features...`, `height about N cm`). Form çocuk yaş aralıkları + ölçü/yüz alanları taşır.
2. **Pozlar mankenden BAĞIMSIZ.** `creative_mannequin_poses` kaldırıldı; yeni `creative_poses` kütüphanesi (migration `2026_06_11_101100/101200`; `tryon_results.pose_id` FK → `creative_poses`). Her poz nötr figürle bir kez önizlenir: `Pose` modeli, `PoseService` (seed+generate), `PosePromptBuilder`/`{Gemini,Mock}PosePreviewComposer`, `GeneratePosePreviewJob`, `PoseController`, route `creative.poses.*`, UI `Pages/CreativePoses.vue` + nav "Pozlar". Eski `MannequinPose` modeli/`MannequinPoseService`/`GeneratePoseJob`/`CreativeMannequinPoses.vue` SİLİNDİ.
3. **Try-on artık iki AI adımı.** `ProductOnModelService::generate` önce seçilen mankeni seçilen pozun yönergesiyle compose eder (mevcut `MannequinPoseComposer`, kimlik referansı = mankenin reference görseli), sonra ürünü giydirir. `queue(product, mannequin, poseIds)` bağımsız poz id'leri alır. UI: `CreativeTryon.vue` artık ürün + manken + (bağımsız kütüphaneden) çoklu poz seçtirir.
- Doğrulandı (mock, gemini key runtime'da boşaltılarak): manken ölçü/yüz prompt'a girdi; 24 poz seed + önizleme; try-on iki adım → product_images. `schema:audit` temiz (tablo/model dengede), `npm run build` temiz.

**Açık riskler / sonraki adımlar (talep gelirse):**
- Kimlik tutarlılığı 20 pozda Gemini'de en iyi-çaba; `destroyPose` ile elle ayıklanır. İstenirse otomatik yüz-benzerlik skoru eklenebilir.
- Her şey MOCK ile doğrulandı. Gerçek kalite için `.env`: `GEMINI_API_KEY` (compose+poz) + `FAL_KEY` (idm-vton try-on). Anahtar yoksa mock'a düşer.
- Queue: job'lar dispatch ediliyor; prod'da `QUEUE_CONNECTION=redis` + `queue:work`/Horizon önerilir (AI uzun sürer).
- Varyant seviyesi giydirme (renk/beden) ve toplu (ürün×manken) kuyruk ileride eklenebilir.

---

## Devam etme talimatı (kendime not)
1. Bu dosyadan sıradaki ⬜ fazı seç.
2. `TaskCreate` ile o fazın adımlarını çıkar, `in_progress` işaretle.
3. Faz bitince: bu dosyada ⬜ → ✅ yap + kısa "yapıldı" özeti ekle, auto-memory
   `project_creative_brandcreative_roadmap` dosyasını güncelle.
4. Doğrulama: mock yol + tinker/araç ile uçtan uca; gerekiyorsa `schema:audit`.
