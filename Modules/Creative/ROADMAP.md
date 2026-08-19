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

## ⬜ Faz F — Sahne/Lokasyon Kütüphanesi (ERTELENDİ — kullanıcı talebiyle plana eklendi)
**Amaç:** Manken giydirme çıktısını sabit stüdyo arka planı yerine dış mekan/lokasyon konseptine
(sokak, plaj, park, kafe terası, çatı vb.) taşımak — kimlik/poz/kıyafet birebir korunarak.

**Ne zaman başlanacak:** Şimdi değil. Karar: önce mevcut Sanal Manken Stüdyosu akışı (Faz A-E +
REVİZYON) en iyi hale getirilecek (kalite/gerçekçilik/açık riskler giderilecek), bu faz ancak ondan
sonra denenecek.

**Bulgu (mevcut mimari neden değiştirilmeden bırakılmalı):** Arka plan şu an 3 yerde stüdyoya kilitli:
`MannequinPosePromptBuilder` (`'Plain seamless light-gray studio background...'`), `GeminiTryOnPromptBuilder`
(kişi+sahneyi birebir koru der), ve paylaşılan `PromptDirectives::camera()` (`"...minimalist photo studio
background"`). Bunları doğrudan değiştirmek, zaten kalibre edilmiş 24 poz × N manken kimlik tutarlılığını
bozma riski taşır.

**Karar (önerilen yaklaşım):** Sahne, poz'un mankenden bağımsızlaştırılmasıyla AYNI idiom — poz/kimlik/
try-on'dan TAMAMEN bağımsız, stüdyo giydirme bittikten SONRA uygulanan 3. bir AI adımı (arka plan
"relight" — Faz 2'deki `AiSceneService`/`GeminiSceneComposer` deseniyle paralel). Orijinal stüdyo görseli
silinmez, sahne versiyonu ayrı kolonda tutulur (geri dönülebilir).

**Fazlar:**
- **F1 — Şema+Model+Config:** migration `creative_locations` (Pose'a paralel: `location_key, label,
  prompt, reference_image_path?, sort_order, status, error, meta`, gerçek `down()`); migration
  `creative_tryon_results`'a `location_id` (nullable FK) + `scene_image_path` (nullable, `staged_image_path`
  korunur); Model `Location`; config `creative.scene.locations` kataloğu (golden-hour sokak, park yolu,
  plaj iskelesi, kafe terası, çatı gün batımı, kırsal yol, şehir ara sokağı...). `schema:audit` temiz.
- **F2 — Prompt+Compose Driver:** stüdyoya özel `PromptDirectives::camera()` bu adımda KULLANILMAZ,
  yerine lokasyona göre ortam-spesifik ışık/lens direktifi. `Contracts/LocationComposerContract`,
  `Ai/LocationRequest.php`, `Drivers/Gemini/LocationPromptBuilder.php`,
  `Drivers/Gemini/GeminiLocationComposer.php`, `Drivers/Mock/MockLocationComposer.php` (mevcut driver
  desenlerinin kopyası). **Kritik prompt kuralı:** kimlik/poz/kıyafet birebir sabit, SADECE arka plan +
  o ortamın ışığı/renk sıcaklığı değişsin (temas gölgesi + ortam ışığının cilt/kumaşa yansıması + doğru
  alan derinliği) — atlanırsa "yapıştırılmış kesik" (cutout-paste) görünümü çıkar, en büyük risk burası.
- **F3 — Orchestration:** `Services/SceneChangeService::apply(TryonResult, Location)` — kaynak stüdyo
  görselini alır, compose eder, `products/{id}/onmodel_scene_{location}_{id}.png` yazar (orijinali
  silmez); `Jobs/GenerateSceneJob` (tries=3, backoff [10,30]). Sadece `status=done`
  (tercihen `review_status=approved`) sonuçlara uygulanabilir.
- **F4 — HTTP+UI:** `TryonController::applyScene()` + route `creative.tryon.scene`; `CreativeTryon.vue`
  sonuç kartına "Sahne değiştir ▾"; `publish()` seçili versiyonu (stüdyo/sahne) `product_images`'a yazar.
  Lokasyon CRUD sayfası opsiyonel/ertelenebilir, MVP'de config kataloğu yeterli.
- **F5 — Doğrulama:** mock uçtan uca (kimlik/kıyafet aynı, arka plan değişti); gerçek `GEMINI_API_KEY`
  ile birkaç lokasyon deneyip ışık/gölge tutarlılığını gözle kontrol et; `schema:audit` + `npm run build`
  temiz.

---

## Faz G — Giysi Parça Tespiti (torchvision fine-tune)
**Amaç:** Yüklenen giysi görselindeki parçaların (yaka, cep, etek, kol ucu vb.) konumunu tespit edip
veritabanında adlandırılmış olarak saklamak; bu bilgiyi Gemini try-on'a ek referans olarak beslemek
("yaka kısmı bu, etek kısmı bu"); ve detay sayfasında tespit kutularını raporlama amacıyla görselleştirmek.

**Lisans kararı (kritik, mimariyi belirledi):** Ultralytics YOLOv8 iç/ticari kullanımda bile Enterprise
lisans gerektirir (AGPL-3.0); DeepFashion2 research-only lisanslı, Fashionpedia'nın kaynak görselleri
karışık lisanslı. Bunun yerine **torchvision** (BSD, `python/.venv`'de zaten kurulu — CLIP sınıflandırıcı
için) tabanlı bir dedektör + **bu mağazanın kendi ürün fotoğraflarıyla fine-tune** yaklaşımı seçildi.
Kullanıcı onayladı: başlangıçta (model eğitilene kadar) parçalar manuel işaretlenir, zamanla model devralır.

- **✅ G.1 — Şema + sözleşme + null sürücü.** Migration'lar: `creative_garment_labels` (kanonik
  parça/tip sözlüğü, otomatik isimlendirme), `creative_garment_scans` (içerik hash'ine göre dedup,
  `detections` JSON), `creative_tryon_results.garment_scan_id`. Model'ler `GarmentLabel`, `GarmentScan`.
  `GarmentPartDetectorContract` + `NullGarmentPartDetector` (`detail_classification` ile aynı
  graceful-degrade deseni) + `CreativeServiceProvider` binding'i (`is_file(weights_path)` kontrolüyle
  ağırlık dosyası yokken otomatik Null'a düşer — kod değişikliği gerekmez).
- **✅ G.2 — Manuel kutu-etiketleme aracı.** `GarmentScanController` + `CreativeGarmentLabeling.vue`
  (tıkla-sürükle dikdörtgen çizme, autocomplete etiket) + `CreativeGarmentScans.vue` (liste) —
  `creative.asset.manage` yetkisiyle. Bootstrap veri toplama VE mevcut otomatik tespitleri düzeltme
  (düzeltilen kutu `source=manual`'a geçer, eğitim verisi kalitesi için güvenilir sayılır).
- **✅ G.3 — Gerçek tespit sürücüsü + orkestrasyon + raporlama.** `PythonGarmentPartDetector` +
  `detect_garment_parts.py` (torchvision Faster R-CNN v2/FCOS, kendini betimleyen checkpoint —
  state_dict + label_map + model_version). `GarmentScanService::scan()` (hash-dedup, kalıcı görsel
  kopyası `garment-scans/{hash}.ext`, otomatik etiket isimlendirme) + `cropsForTryOn()` (yüksek güvenli
  parçaları GD ile kırpıp `GarmentTryOnContract`'ın mevcut `garmentExtras` mekanizmasına ekler —
  `GeminiTryOnPromptBuilder::describeExtras()` HİÇ değişmeden bunu "image #N is the SAME garment,
  '<parça>' view" diye prompt'a çevirir). `ProductOnModelService::generate()`'a kablolandı.
  `CreativeTryonDetail.vue`'de SVG bbox overlay kartı; `CreativeReviewReportCommand::summarizeDetections()`
  + `CreativeReviewReportShow.vue` kartı (sıfır-tespit oranı, parça frekansı, kaynak dağılımı —
  G.3b'den itibaren manuel/otomatik/zero-shot üç kova).
  **Not:** Bu oturumda model dosyası (`garment_parts_latest.pt`) HENÜZ YOK — fine-tune
  sürücüsü bağlanmıyor. `zero_shot.enabled=false` (varsayılan) iken `NullGarmentPartDetector`
  aktif, tüm taramalar sıfır tespitle döner. G.3b açıldığında bunun yerine bootstrap
  öneri kutuları üretilir (aşağıya bkz.) — ikisinde de manuel etiketleme aracı devrede.
- **✅ G.3b — Zero-shot bootstrap dedektörü.** G.3'ün fine-tune sürücüsü ağırlık
  dosyası olmadan çalışamıyor, ağırlık dosyası da G.4'ün ~40 manuel örnek/etiket
  eşiğine ulaşana kadar üretilemiyordu — ve tek örnek toplama yolu her seferinde
  ücretli bir giydirme üretimi tetiklemekti (kullanıcı geri bildirimi: "bu alan
  karışacak/şişecek"). `PythonZeroShotGarmentPartDetector` +
  `detect_garment_parts_zeroshot.py` — OWLv2 (`transformers`, Apache-2.0, YOLO'nun
  aksine ticari kullanımda sorunsuz) ile İngilizce sabit sorgu sözlüğüyle (bkz.
  `classify_garment_detail.py`'nin CATEGORIES deseni) eğitim verisi olmadan öneri
  kutuları üretir. Her tespit `source='zeroshot'` damgalanır
  (`GarmentScanService::normalizeAndNameLabels`) — bu yüzden `cropsForTryOn()`
  ile `creative:train-garment-detector` (ikisi de `source='manual'`+`'auto'`
  filtreler, aşağıdaki 2026-07-19 güncellemesine bkz.) tarafından bir insan
  `/creative/garment-scans/{id}` etiketleme aracında **Onayla**/**Reddet**
  demeden asla görülmez; Onayla mevcut `updateAnnotation` endpoint'ini aynı
  bbox/label ile çağırır (backend zaten source'u manual'a çeviriyor, yeni
  endpoint YOK). `CreativeServiceProvider`
  binding sırası: fine-tune ağırlığı varsa her zaman o (G.4 koşunca KOD DEĞİŞİKLİĞİ
  GEREKMEZ garantisi korunur) → yoksa `zero_shot.enabled` ise bu sürücü → yoksa Null.
  Varsayılan KAPALI (`CREATIVE_GARMENT_ZERO_SHOT_ENABLED=false`) — yeni
  `transformers` bağımlılığı + ilk çalıştırmada ~1GB model indirmesi ekliyor.
  **Ölçülen gerçek maliyet (2026-07-19, prod sunucusu, 2 vCPU/GPU yok):**
  tek görsel çıkarımı 190-230sn arası CPU süresi alıyor (roadmap'te önceden
  yazılan "birkaç saniye" tahmini YANLIŞ çıktı; sentetik test görselinde
  189sn, gerçek bir ürün görselinde 229.5sn ölçüldü) — bu yüzden
  `CREATIVE_GARMENT_ZERO_SHOT_TIMEOUT` varsayılanı (60sn) her taramayı öldürür,
  güvenli marj için 300sn'ye çıkarıldı (env). `GarmentScanService::scan()`
  image_hash ile dedup ettiği için bu maliyet benzersiz giysi görseli başına
  BİR KEZ ödenir (aynı ürünün 20 pozu için değil) ama ilk isabet
  `GenerateOnModelJob` içinde olur — bu yüzden job `$timeout`'u 300'den 700'e
  çıkarıldı (worker'ın supervisor `--timeout=350`'sini job-level `$timeout`
  override ettiği doğrulandı, `Worker::timeoutForJob()`). Gerçek bir ürün
  görseliyle (`products/1/onmodel_staged_1.png`) uçtan uca doğrulandı: DI
  container `PythonZeroShotGarmentPartDetector`'ı bağlıyor, 1 tespit döndü
  (`etek`, confidence 0.1534, `source=zeroshot`).
  **Bulunan/düzeltilen iki hata (canlı test sırasında, 2026-07-19):**
  (1) `resolveDriverName()` yalnız `PythonGarmentPartDetector`'ı tanıyordu,
  `PythonZeroShotGarmentPartDetector` de 'null' olarak damgalanıyordu —
  `match` ile üç sürücü de (`python`/`zeroshot`/`null`) ayrıştırılacak şekilde
  düzeltildi. (2) **Daha kritik:** `GarmentScanService::scan()` bir görseli
  `status=done` ise (image_hash eşleşse) sürücü değişmiş olsa bile SESSİZCE
  eski kaydı dönüyordu — zero-shot açılmadan ÖNCE (Null sürücüyle) taranmış
  bir ürün görseli zero-shot açıldıktan SONRA tekrar giydirilse bile hâlâ eski
  boş tespiti gösteriyordu (kullanıcı canlı testte bunu yakaladı: yeni
  giydirmeler `/creative/garment-scans`'te tespitsiz görünüyordu). Düzeltme:
  `driver='null'` + `status=done` olan kayıtlar, o an bağlı sürücü null
  değilse "stale" sayılıp yeniden taranıyor; gerçek bir sürücüyle (`python`/
  `zeroshot`) üretilmiş `done` kayıtlar hâlâ olduğu gibi cache'leniyor. Sentetik
  bir görselle (fake `driver=null` kaydı oluşturup) uçtan uca doğrulandı: aynı
  satır (`id` değişmedi), yeniden tarandı, `driver` `zeroshot`'a güncellendi.
  `creative:review-report`'un kaynak dağılımı artık üç kova (`manual`/`auto`/
  `zeroshot`) raporluyor ki onaysız AI önerileri fine-tune modelin gerçek
  tespitleriymiş gibi görünmesin.
  **Kapsam genişletmesi (kullanıcı kararı, 2026-07-19):** `cropsForTryOn()`
  başlangıçta yalnız `source='auto'` (G.4 fine-tune çıktısı) kabul ediyordu —
  yani Onayla'nan zero-shot tespitleri (source→'manual') SADECE eğitim
  verisine giriyordu, try-on üretimine hiç yansımıyordu (canlı testte
  kullanıcı bunu fark etti: "otomatik tespit edilmedi... giydirirken bu
  detayları kullanmamış"). Kullanıcı fine-tune'u beklemeden onaylanan
  tespitlerin try-on kalitesini de iyileştirmesini istedi. Filtre artık
  `source in ['auto','manual']` kabul ediyor. **Yan etki:** `updateAnnotation`
  hem elle çizilen hem Onayla'nan zero-shot kutuları aynı `source='manual'`
  değerine yazdığı için (ayrım yapan ayrı bir alan yok) bu değişiklik
  G.2'den beri var olan, daha önce try-on'a hiç girmeyen elle-çizilmiş
  kutuları da artık aktif hale getiriyor — bu kasıtlı ve tutarlı bir
  genişleme (bir insanın onayladığı/çizdiği kutu = güvenilir), ayrı bir
  onay mekanizması eklenmedi.
- **⬜ G.4 — Fine-tune eğitim komutu (ilk koşu bekliyor).** `train_garment_parts.py` (COCO-format export
  → torchvision transfer learning) + `creative:train-garment-detector` (`min_examples_per_label` eşiği,
  yetersiz veri varken zarifçe çıkar, crash etmez) yazıldı ama **çalıştırılmadı** — yeterli manuel
  etiketli örnek (varsayılan eşik: etiket başına 40) birikene kadar admin bunu elle tetiklemeli.
  Bilinçli olarak zamanlanmadı (routes/console.php'ye eklenmedi) — eğitim CPU'da uzun sürebilir ve
  model kalitesi admin gözden geçirmesi gerektirir.
- **✅ G.5 — Garment Identity Preservation (Gemini Vision parça analizi).** Kullanıcı geri bildirimiyle
  (10 madde, üçü kritik önceliklendirildi) "parça analizi" kapsamı bir kimlik-koruma sistemine
  dönüştürüldü. `GeminiClient::analyzeImage()` (görsel-girdi+metin-çıktı, ilk kez eklendi).
  `GarmentPartAnalyzerContract`/`GeminiPartAnalyzer`/`MockPartAnalyzer` — her crop için renk/desen/
  doku/kumaş/dikiş/donanım **enum tabanlı** (OTHER+raw_text kaçış kapılı), **confidence'lı**,
  **versioned zarflı** (`analysis_version`/`model`/`prompt_version`/`generated_at`/`data`) analiz.
  `GarmentIdentitySummarizerContract`/`GeminiIdentitySummarizer` — bütünsel "ayırt edici en fazla 5
  özellik" çağrısı (`GarmentScan.identity_summary`, ayrı opsiyonel bayrak). `creative_garment_labels`'a
  `preservation_category`/`default_priority` (migration, seed: `garment_detection.label_defaults`
  config'i — "logo" HER ZAMAN identity/critical, ürüne göre değişmez). **`GarmentIdentityRuleEngine`**
  (`Services/GarmentScanService.php` DEĞİL, ayrı saf-PHP servis, hiç AI/DB çağrısı yapmaz) — confidence
  eşiği altındaki alanları eler, nihai önceliği `max(etiket taban değeri, Gemini'nin örnek-bazlı
  değerlendirmesi)` ile hesaplar (taban değerin ALTINA asla düşmez), critical/high parçalar için
  "do not redesign/replace/invent" direktifi + tek bir "protect list" cümlesi üretir — ham analiz JSON'u
  ASLA doğrudan prompt'a yazılmaz, her zaman bu katmandan geçer. `GarmentScanService::scan()` artık her
  crop'u KALICI yazar (`garment-scans/{hash}/{detection_id}.png`) + `crop_hash` (sha256) hesaplar +
  gerekirse bicubic upscale uygular (min 768px kenar — 3 sabit çözünürlük yerine uyarlamalı tek
  çözünürlük, kullanıcıyla mutabık kalınan tek ayarlama). `GeminiTryOnPromptBuilder::build()` artık
  `protectListSentence` (prompt'un en başına) + her parça için Rule Engine'in `statement`'ını alır.
  `CreativeTryonDetail.vue`'ye öncelik rozetleri + enum değerleri + "Ürünü Ayırt Eden Özellikler" şeridi
  eklendi. Testler: `GarmentIdentityRuleEngineTest` (9 senaryo, tamamı saf/DB'siz) +
  `GeminiTryOnPromptBuilderTest`e 3 yeni senaryo. **Not:** `analysis.enabled=false` (varsayılan) —
  Mock sürücüler devrede, sıfır davranış değişikliği (G.1-G.3 ile aynı garanti).

**Devam etmek için:** `CREATIVE_GARMENT_ZERO_SHOT_ENABLED=true` açılıp (G.3b) etiketleme aracında
Onayla/Reddet ile örnek biriktirme hızlandırılabilir. Yeterli manuel onaylı örnek biriktikten sonra
`php artisan creative:train-garment-detector --dry-run` ile eşik durumunu kontrol et, ardından
(dry-run olmadan) çalıştır. Başarılı olursa `garment_parts_latest.pt` oluşur ve
`CreativeServiceProvider`'daki `is_file()` kontrolü sayesinde KOD DEĞİŞİKLİĞİ GEREKMEDEN fine-tune
tespit devreye girer (zero-shot'un önüne otomatik geçer).

---

## ✅ Faz — AI Copywriting (marka standardına uygun görsel-üstü metin)
**Amaç:** Şablonun `headline`/`sub_headline`/`cta_button` slotları için, admin'in `BrandKit`'e
yazdığı kalıcı kriterlere (design_brief/tone/cta_phrases/banned_words) uygun metin AI ile üretilsin.

- `brand_kits` tablosuna `design_brief`/`tone`/`cta_phrases`/`banned_words` (migration, gerçek
  `down()`); `BrandTokenService::tokens()['criteria']` tek doğruluk kaynağı.
- `Services/Ai/CopyRequest.php` + `Contracts/CopyGeneratorContract.php` + `Drivers/Gemini/
  GeminiCopyGenerator.php` (`GeminiPartAnalyzer` ile aynı versioned+confidence zarf deseni) +
  `Drivers/Mock/MockCopyGenerator.php`.
- `Services/CreativeCopyRuleEngine.php` (saf PHP, `GarmentIdentityRuleEngine` ile aynı disiplin):
  confidence eşiği, CTA kapalı-sözlük coerce (drop değil), yasaklı kelime taraması (tüm alanı düşürür),
  slot genişliğine göre kaba karakter tavanı kırpması. Test: `tests/Unit/Creative/
  CreativeCopyRuleEngineTest.php`.
- `CreativeRenderService::buildCopy()` — şablonun TANIMLADIĞI her copy slot'unu `values`'a HER ZAMAN
  yazar (boşsa `''`) — aksi halde `apply_slots.py`'nin placeholder davranışı (`elem.text = slot["key"]`)
  literal `"headline"` gibi metnin PNG'ye sızmasına yol açar (bu oturumda doğrulanan kritik bulgu).
  Sonuç `meta.copy`/`meta.copy_ai_raw` (yeni tablo yok — caption/ai_scene presedansı).
  Sahne üretimi de aynı `design_brief`'i `GeminiPromptBuilder`'a mood ipucu olarak alır.
- UI: `CreativeStudio.vue`'ye `useAi` ile birebir aynı desende `useCopyAi` toggle'ı ("✍️ AI metin").
  Denetim şekli bilinçli olarak **sessiz düzeltme/düşürme** — ayrı bir onay gate'i yok, metin
  Python/SVG motoruyla (font glyph render) basıldığı için yazım hatası riski yapısal olarak yok.

---

## ✅ Faz H — fal.ai FLUX Kontext ile tam AI kompozisyon (ön koşul (b) karşılandı → Faz J)
Kullanıcının fal.ai'de (Black Forest Labs **FLUX.2**) elle denediği bir tam-kompozisyon post örneği
(2026-07-21) incelendi: görsel kalitesi yüksek ama CTA metninde somut bir yazım hatası bulundu
("Şimdi keşbet" — doğrusu "keşfet") ve sahnedeki ürün kullanıcının gerçek ürün fotoğrafı DEĞİLDİ,
modelin hayal ettiği bir sahneydi. Python/SVG render motorunda bu iki risk yapısal olarak yok
(glyph render edilir, gerçek ürün fotoğrafı doğrudan kullanılır) — bu yüzden mevcut mimari (yukarıdaki
faz) bu riski taşımaz.

Araştırıldı: `FLUX.2 [pro] Edit` / `FLUX Kontext [pro]` varyantı referans görsel + metni birlikte
işleyip "orijinal öğeleri koruyarak" sahne dönüşümü yapabiliyor — yani gerçek ürün fotoğrafını koruma
teorik olarak mümkün, ama HİÇ test edilmedi. Fiyat: `fal-ai/flux-2` ~$0.012/megapiksel.

**Başlamadan önce gerekli olan iki ön koşul vardı:** (a) gerçek ürün fotoğraflarıyla çoklu örnekte
ürün sadakati + Türkçe metin doğruluğu ölçülmeli; (b) öznel bir "brand similarity skoru" DEĞİL,
somut/ölçülebilir bir kontrol (ör. üretilen metni OCR/Gemini-vision ile istenen metinle birebir
karşılaştırma).

**Güncelleme (2026-07-21, Faz J):** Ön koşul (b) `LayoutConstraintEngine` + OCR gate ile karşılandı
— aşağıdaki Faz J'ye bakın. Ön koşul (a) hâlâ karşılanmadı (gerçek ürün fotoğraflarıyla ölçüm
yapılmadı); bu yüzden `composition.driver` varsayılanı `mock` ve `ocr.enabled=false` olarak kalıyor
— özellik kodda tam ama prod'da kapalı.

---

## 🟡 Faz J — AI Kompozisyon (fal.ai Flux.2) + LayoutConstraintEngine (kod tamam, prod'a kapalı)
Faz H'nin (b) ön koşulunu karşılamak için, `CreativeCopyRuleEngine` ile aynı disiplinde
("AI çıktısı asla ham kabul edilmez, üretimden SONRA deterministik kurallara karşı doğrulanır")
ikinci, opsiyonel bir render path eklendi. Mevcut SVG yolu (varsayılan) hiç değişmedi.

**Yapılanlar:**
- `CreativeAsset.meta.render_engine` (`svg` varsayılan | `ai_compose`) — `use_ai`/`use_copy_ai`
  ile birebir aynı toggle mekanizması, `GenerateCreativesRequest`/`CreativeStudioController`/
  `CreativeStudio.vue`'de uçtan uca bağlandı (toggle yalnızca `composition.driver=fal` VE key
  varsa görünür; mock'ken sessizce gizli).
- `Services/Ai/Contracts/CompositionComposerContract` + `Drivers/Fal/FalFluxComposer` (gerçek
  çağrı) + `Drivers/Mock/MockCompositionComposer` (key'siz uçtan uca test — bilinen sabit pikselde
  headline "yakar").
- `Services/Vision/TextRecognizerContract` + `PythonTesseractTextRecognizer` +
  `NullTextRecognizer` + `python/ocr_text.py` (`pytesseract.image_to_data`).
- `Services/LayoutConstraintEngine` — `checkIntendedText()` (render'dan önce, ucuz fail-fast:
  headline/CTA kelime-satır limitleri) + `evaluate()` (OCR sonrası: fuzzy metin eşleşmesi +
  safe-margin kontrolü). Saf PHP, AI/DB çağrısı yok — `CreativeCopyRuleEngineTest` stiliyle
  `LayoutConstraintEngineTest` yazıldı (9 test, geçiyor).
- `Services/Exceptions/CompositionConstraintException` (geçici, `PermanentRenderException`'dan
  TÜREMEZ) — mevcut `GenerateCreativeJob` retry/backoff mekanizmasına dokunmadan job'ı yeniden
  dener; tükenirse `status=failed`, `error` alanına ihlal listesi yazılır. Review pipeline'a
  (`review_status`, `ImagePendingReviewNotification`) sıfır değişiklik — constraint'i geçemeyen
  asset asla insan reviewer'a ulaşmaz.
- **Güvenlik kilidi doğrulandı:** `composition.ocr.enabled=false` iken `ai_compose` motoru hiç
  çalışmıyor, `PermanentRenderException` ile derhal reddediliyor.
- `Services/CreativeRenderService::generateAiComposition()` orkestrasyonu: referans görselleri
  topla → `buildAiCopy()` ile headline/CTA üret → fail-fast precheck → OCR kapalıysa hemen reddet
  → `composer->compose()` → `textRecognizer->recognize()` → `layoutConstraints->evaluate()` →
  geçerse mevcut `enhancer->enhance()`'den itibaren SVG yoluyla aynı kuyruğa katılır.

**Prod'a açmadan önce kalanlar (bilerek yapılmadı — varsayılanlar kapalı: `driver=mock`,
`ocr.enabled=false`):**
1. fal.ai Flux.2 tam endpoint id + istek/yanıt şekli doğrulanmadı (`fal-ai/flux-2` tahmini,
   çoklu referans görsel + literal metin talimatı kabul ettiği teyit edilmedi).
2. Sunucuda `tesseract-ocr` CLI + `pytesseract` kurulu değil (`apt install tesseract-ocr
   tesseract-ocr-tur` + `python/requirements.txt`).
3. `text_match_min_similarity` (varsayılan 0.85) gerçek Flux.2 çıktılarıyla kalibre edilmedi.
4. Manken referansının `CompositionRequest`'e bağlanıp bağlanmayacağı netleşmedi (MVP şu an
   sadece ürün görseliyle çalışıyor).
5. Faz H'nin (a) ön koşulu — gerçek ürün fotoğraflarıyla (5-10 örnek) çoklu test — yapılmadı;
   ürün sadakati ve Türkçe CTA/headline doğruluğu manuel gözlemlenmeden `driver=fal`/
   `ocr.enabled=true` prod'da açılmamalı.

## ⬜ Faz I — Sınırlı "marka analizi" (geçmiş post'lardan stil önerisi, ERTELENDİ)
Kullanıcı sistemin geçmiş post'lardan otomatik öğrenmesini istedi. Instagram/Facebook'tan otonom
çoklu-kanal veri çekme (kullanıcının paylaştığı bir vizyon dokümanındaki "Brand Analyzer" kavramı)
araştırma-seviyesi güvenilirlik riski taşıyor (subjektif "minimalism score" gibi metrikler bir vision
LLM'den tutarlı çıkmaz) ve ayrı bir entegrasyon/ToS konusu — bu haliyle YAPILMAYACAK.

Daha dar, gerçekçi bir versiyon: admin birkaç **temsili referans post** görseli yükler (harici
scraping yok) → `GeminiPartAnalyzer` ile birebir aynı desende (versioned+confidence zarf, Gemini
Vision görsel-girdi/metin-çıktı) bir stil analizi yapılır → sonuç ASLA otomatik uygulanmaz, admin'e
"öneri" olarak gösterilir, kabul ederse `design_brief`/`tone` alanlarına elle/onaylı şekilde yansır.
Faz H gibi bu da ayrı bir oturumda kapsamı netleştirilip planlanmalı.

---

## 🆕 Vizyon — "Render-Centric'ten Creative-Centric'e" (kullanıcı önerisi, mimari değerlendirme, 2026-07-22)
Kullanıcı, sistemin `BrandKit → Template → Render → Review` zincirinin ötesine geçip bir
"AI Creative Operating System" olmasını istedi: CreativeBrief, BrandBrain, Layout Intelligence,
Scene Graph, genişletilmiş Constraint Engine, Brand Similarity Score, Learning System, otomatik
layout üretimi. **Teşhis doğru** — sistem bugün gerçekten render-merkezli. Ama önerilen 12
kavramın çoğu SIFIRDAN değil: modül üç yıldır aynı disiplini ("AI çıktısı asla ham kabul edilmez,
deterministik bir kural motorundan geçer" — bkz. `GarmentIdentityRuleEngine`,
`CreativeCopyRuleEngine`, `LayoutConstraintEngine`) tekrar tekrar uyguluyor. Aşağıda her kavram
mevcut karşılığına bağlanıyor, gerçekten yeni olan ayrılıyor, ve **Faz I'de zaten reddedilmiş bir
tuzak** işaretleniyor.

### Zaten var / bugün genişledi — yeniden adlandırma, sıfırdan inşa değil
| Kullanıcının önerisi | Mevcut karşılığı |
|---|---|
| Layout Definition (JSON) → SVG üretilsin | **Bugün eklendi:** `config('creative.template_presets')` = Layout Definition; `TemplateGeneratorService::buildSvg()` = tam olarak "SVG üretilen artefakt" ilkesi. Faz K bunu config'ten DB'ye taşımaktan ibaret. |
| Scene Graph (Layout → Prompt Builder sözleşmesi) | `Services/Ai/SceneRequest.php` + `Drivers/Gemini/GeminiPromptBuilder.php` zaten bu ara-katmanı informal olarak oynuyor; resmi bir sözleşmeye çıkarmak küçük bir refactor. |
| Constraint Engine | Zaten ÜÇ tane var: `LayoutConstraintEngine` (OCR+metin), `CreativeCopyRuleEngine` (metin uzunluğu/yasaklı kelime), `GarmentIdentityRuleEngine` (kimlik koruma). Kullanıcının istediği `whitespace>=%22`, `hero.coverage>=65%` gibi kurallar — bunların BİRLEŞTİRİLMİŞ/genişletilmiş hali, net yeni bir sistem değil. |
| Design Tokens genişletmesi | `brand.defaults` + preset geometrisi zaten oransal (0-1) — spacing/safe-area kavramı fiilen var, isimlendirilmemiş. |
| Learning System (BrandBrain "öğrenir") | `CreativeStudioController::stats()` (Faz 5) zaten şablon bazlı başarı oranı/render süresi topluyor — kapsamı preset/layout/CTA bazına genişletmek, YENİ bir öğrenme sistemi kurmak değil. |

### Gerçekten yeni
- **CreativeBrief** — bugün yok. Kullanıcı template_id seçiyor (`GenerateCreativesRequest`);
  brief'ten layout/preset türetmek net bir katman eksikliği.
- **Layout Recommendation Engine** — brief + geçmiş performans istatistiğinden preset seçimi.
- **LayoutGeneratorContract** (Claude → Layout JSON) — otomatik layout üretimi bugün yok
  (`TemplateGeneratorService` sabit preset'lerle çalışıyor, LLM'den JSON üretmiyor).

### ⚠️ Durdurulması gereken tek madde: Brand Similarity Score (0-100 alt skorlar)
Bu, **Faz I'de** ("Sınırlı marka analizi") kullanıcının kendi onayıyla zaten değerlendirilip
ERTELENDİ: *"subjektif 'minimalism score' gibi metrikler bir vision LLM'den tutarlı çıkmaz"*.
"Typography: 97, Colors: 100, Brand Match: 98" gibi tek-sayı skorlar tam olarak bu kategoriye
giriyor — aynı görseli bir vision LLM'e iki kez sorsanız iki farklı sayı alırsınız, sayı
"tutarlı" göründüğü için de yanıltıcı bir güven yaratır. **Öneri:** reviewer'a sayı UYDURMAK
yerine, zaten var olan deterministik kontrollerin (LayoutConstraintEngine/CreativeCopyRuleEngine/
GarmentIdentityRuleEngine) geçti/kaldı raporunu bir "kontrol kartı" (checklist) olarak göster —
her satır ölçülebilir bir kuralın gerçek sonucu, uydurulmuş bir "91/100 minimalism" değil.

### Önerilen fazlandırma (⬜, sıradaki oturumda seçilecek)
1. **Faz K — Layout Definition tablosu.** `template_presets` config'ini `creative_layouts`
   tablosuna taşı (migration, gerçek `down()`); `TemplateGeneratorService` DB'den okusun. Admin
   UI'da CRUD. Düşük risk — bugünkü kodun üzerine ince bir katman.
2. **Faz L — CreativeBrief.** Yeni model (Eloquent, DTO değil — CLAUDE.md): campaign/objective/
   platform/audience/emotion/style/text_density/cta alanları. `GenerateCreativesRequest`'e
   `brief_id` opsiyonel alternatifi eklenir (`template_id` hâlâ çalışır — kırılma yok).
3. **Faz M — Layout Recommendation Engine.** Saf PHP kural motoru (AI çağrısı YOK, mevcut
   `*RuleEngine` disipliniyle aynı): brief + `creative_layouts` + geçmiş `review_status` oranına
   göre sıralı öneri listesi döner, admin son kararı verir (otomatik seçip render etmez).
4. ✅ **Faz N — Constraint Engine: layout geometrisi kuralları (yapıldı).** Kodu yazmadan önceki
   "3 motoru birleştir" çerçevesi kod okunduktan sonra YANLIŞ çıktı (bkz. aşağıdaki bulgu) —
   bunun yerine `LayoutConstraintEngine`'e render'a hiç gerek duymayan, saf slot-geometrisi
   tabanlı yeni bir metot eklendi.
5. **Faz O — LayoutGeneratorContract (Claude → Layout JSON).** Faz H/J'nin idiomuyla: LLM ham
   SVG değil, şemaya karşı doğrulanan Layout JSON üretir (bkz. Faz K); geçersiz JSON'sa SVG'ye
   hiç çevrilmez. `driver=mock` ile başlar (Faz H/J presedansı).
6. **Faz P — BrandBrain (deterministik aggregate, vision-skor YOK).** `stats()`'ı preset/CTA/ton
   bazına genişlet; Layout Recommendation Engine (Faz M) bu istatistiği girdi olarak kullanır.

**Bağımlılık sırası:** K→L→M zorunlu (recommendation, brief+layout'a muhtaç); N ve O paralel
yapılabilir; P, M'den önce anlamsız (istatistik önce toplanmalı).

---

## ✅ Faz N — Constraint Engine: Layout Geometrisi Kuralları (yapıldı, 2026-07-22)
**Bulgu (plan revizyonu):** "3 mevcut motoru (`LayoutConstraintEngine`/`CreativeCopyRuleEngine`/
`GarmentIdentityRuleEngine`) tek sınıfta birleştir" fikri kod okunduktan sonra terk edildi — üçü
farklı domain'lerde (post-OCR layout / AI copy metni / garment try-on prompt direktifi), farklı
orkestratörlerden (`CreativeRenderService` ×2, `ProductOnModelService`), farklı girdi
şekilleriyle çağrılıyor; ortak çağrı noktaları yok. Zorla birleştirmek CLAUDE.md'nin "gereksiz
soyutlama ekleme" ilkesini ihlal ederdi. Kullanıcının somut örnekleri (`hero.coverage`,
`whitespace`, `text_overlap`, `cta.count`, `logo.visible`) aslında hepsi **slot GEOMETRİSİ**
üzerine kurallar — bu, zaten var olan `LayoutConstraintEngine`'in domain'i içinde, tek bir yeni
metotla karşılanıyor. `CreativeCopyRuleEngine`/`GarmentIdentityRuleEngine`'e dokunulmadı.

- `LayoutConstraintEngine::checkSlotGeometry(slots, width, height)` — render'a hiç gerek
  duymadan `CreativeTemplate.slots` JSON'undan hesaplar (hem manuel hem `TemplateGeneratorService`
  çıktısı şablonlar için aynı): yinelenen slot anahtarı, hero/ürün kapsama oranı, boşluk oranı,
  slot çakışması, (opsiyonel) logo slotu zorunluluğu. Tuvalin ≥%60'ını kaplayan görsel slotu
  ("tam-kapak arka plan fotoğrafı") kasıtlı zemin katmanı sayılıp hem boşluk hem çakışma
  hesabından hariç tutulur — aksi halde bugünkü `product_full_bleed` preset'i (fotoğraf %100
  kapak) her zaman "çakışıyor"/"boşluk yok" yanlış-pozitifi verirdi.
  **Bulunan iki yanlış-pozitif (test sırasında, gerçek preset verisiyle):** (1) metin kutusu
  yüksekliği ilk denemede `font_size × 1.3` (tam satır yüksekliği) idi — komşu elemanlarla olan
  kasıtlı boşlukları çakışma sayıyordu; `× 0.85` (kaba cap-height) yapıldı. (2) çakışma kontrolü
  `data-w`'yi (AI metin kırpma için "izin verilen AZAMİ genişlik", gerçek render genişliği değil)
  olduğu gibi kullanınca, geniş max-width'e sahip bir headline'ın sağdaki bağımsız bir CTA
  kolonuyla "çakıştığı" yanlış çıkıyordu — çakışma kontrolü için metin genişliği
  `min(data-w, font_size×4)` ile küçük tutuldu (boşluk hesabı hâlâ `data-w`'nin tamamını kullanır,
  yalnız çakışma kontrolü daraltıldı).
- Config: yeni `creative.constraints.layout.*` (`hero_coverage_min_pct=0.30`,
  `whitespace_min_pct=0.08`, `require_logo_slot=false`, `overlap_tolerance_px=4`) — mevcut
  `composition.constraints`/`ai.copy`/`garment_detection.analysis` eşiklerine ve onların
  `.env` değişkenlerine DOKUNULMADI (prod uyumluluğu).
  `CreativeTemplateController::index()` her şablon için `constraints` (`{passed, violations,
  metrics}`) döner (ekstra sorgu yok — `slots` zaten çekiliyordu). **Sert engelleme YOK** —
  `store()`'daki uyarı deseniyle aynı: ihlal varsa şablon yine kullanılabilir, yalnız görünür
  uyarı gösterilir (Faz J'nin `ai_compose` sert-engelleme kararı burada uygulanmadı — o
  doğrulanmamış AI pikselleri içindi, burada deterministik/insan-onaylı bir SVG var).
- UI: `CreativeTemplates.vue` — liste öğesinde `⚠ N` rozeti (ihlal varsa, `.tpl-off` rozetiyle
  aynı konumda), tasarımcı panelinde ihlal listesi.
- Test: `tests/Unit/Creative/LayoutConstraintEngineTest.php`'e 6 yeni test — bugün üretilen iki
  preset'in GERÇEK slot çıktısıyla geriye-dönük doğrulama (`passed=true`) + yinelenen
  anahtar/küçük hero/çakışma/boş-slot senaryoları. `npx vite build` + `php artisan test
  --filter=Creative` temiz (bu oturumdaki tek ilgisiz hata: `MannequinPromptBuilderTest`,
  önceden var olan dalda, bu değişiklikle ilgisiz).

## ✅ Faz Q — Renk Sadakati (tryon renk sapması, denetim + kilit, prod'a kapalı)
Kullanıcı gözlemi: try-on'da ürünün rengi Gemini'nin giydirme adımında sapabiliyor.
Kullanıcının 3 katmanlı planı: (1) çıktıyı orijinal renge LAB uzayında kilitleme,
(2) her üretimden sonra otomatik Delta E denetimi, (3) prompt'ta rengi
isimlendirmeden negatif kısıt. Kod okunduktan sonra iki bulgu tasarımı belirledi:
var olan zero-shot giysi dedektörü (OWLv2) görsel başına 190-230sn CPU
(ROADMAP Faz G.3b) — canlı bir gate için kullanılamaz; bunun yerine
`ProductOnModelService::generate()`'ın zaten ürettiği giydirme-ÖNCESİ (`$posed`)
ve giydirme-SONRASI (`$out`) görselleri arasındaki piksel farkı (diff-mask) giysi
bölgesini sıfır ek AI maliyetiyle veriyor.

- **Python** (`python/_color_common.py`, `_svgcommon.py` deseniyle import-only):
  numpy ile ICC'siz sRGB↔Lab dönüşümü, diff-mask (+ PIL Min/MaxFilter ile
  gürültü temizliği, scipy YOK), köşe-rengi arka plan varsayımıyla orijinal
  üründe foreground maskesi (`prepare_garment.py::_trim_flat_border` fikrinin
  maskeye çevrilmiş hali), CIE76 Delta E. `color_audit.py` (stdin JSON → stdout
  JSON: delta_e/mask_ratio/confidence) ve `color_lock.py` (stdin JSON → stdout
  PNG: L kanalı KORUNUR, a/b kanalları `strength` oranında orijinale kaydırılır,
  düşük-güvenli maskede görsele DOKUNULMAZ) — ikisi de `opencv-contrib-python`
  GEREKTİRMEZ (bu sunucuda zaten kurulu değil), yalnız numpy+Pillow (zorunlu,
  zaten kurulu — sistem `python3`'te doğrulandı).
- **PHP:** `Services/Vision/ColorAuditorContract`+`Python`/`NullColorAuditor`
  (`measure()`, PythonTesseractTextRecognizer ile aynı Process iskeleti, DTO
  YOK — plain array döner) ve `Services/Enhancement/ColorLockContract`+
  `Python`/`NullColorLock` (`apply()`, PythonGarmentPreparer ile aynı iskelet,
  hata/düşük-güven → orijinal görsel aynen döner). `CreativeServiceProvider`'a
  diğer tüm Python-adım binding'leriyle BİREBİR aynı desende iki `bind()`.
  `ProductOnModelService::generate()`: tryOn → `colorLock->apply()` (kapalıysa
  no-op) → `colorAuditor->measure()` (try/catch SARILI, job'ı ASLA düşürmez) →
  `enhancer->enhance()`. Sonuç `meta.color_audit`'e yazılır (yeni migration/
  kolon YOK — Faz 5 `render_ms` presedansı).
- **Prompt (paralel, Q3):** `GeminiTryOnPromptBuilder::build()`'e rengi
  İSİMLENDİRMEDEN negatif bir kısıt cümlesi eklendi ("colorimetrically
  identical... no color grading... regardless of the scene's ambient lighting
  color"). Paylaşılan `PromptDirectives::camera()`'a (manken/poz/tryon'un ÜÇÜ
  de kullanıyor, "warm-toned" ışık direktifi içeriyor) BİLEREK dokunulmadı —
  blast radius büyük, yalnız tryon'a özel ek cümle tercih edildi.
- **Rapor + UI:** `CreativeReviewReportCommand::summarizeColorAudit()`
  (ort. ΔE, eşik-üstü oran, güvenli/toplam ölçüm) → `CreativeReviewReportShow.vue`
  kartı. `TryonController::show()` → `meta.color_audit` → `CreativeTryonDetail.vue`
  4. kpi-card'ı (ΔE rozeti, renk: ≤5 yeşil / ≤10 sarı / üstü kırmızı — kullanıcının
  kendi tahmini eşikler, henüz kalibre edilmedi).
- **Config `creative.color_fidelity.*`:** `audit.enabled`/`lock.enabled` İKİSİ
  DE varsayılan KAPALI (Faz J OCR-gate presedansı — kod tam ama gerçek Delta E
  dağılımı ölçülmeden bir reddet eşiği/agresif `strength` keyfi olur).
  **Sıra:** önce `audit` açılır (ölçek/sorun boyutu ölçülür), gerçek verilerle
  `lock.strength`/eşikler kalibre edildikten SONRA `lock` açılır.
- **Bilinçli kapsam dışı (v1):** sert reddet+yeniden-üret gate'i YOK (yalnız
  ölçer/bilgi rozeti gösterir — Faz N "görünür uyarı, sert engelleme yok"
  presedansı); desenli ürünlerde perspektif-warp YOK (LAB a/b kaydırması
  desende de ortalama rengi düzeltir ama deseni yeniden hizalamaz — bilinen
  sınırlama, talep gelirse ayrı bir faz).
- Doğrulandı: sentetik renk-kaymış test görseliyle `color_audit.py`/
  `color_lock.py` uçtan uca (ΔE 14.12 → kilit sonrası 9.04, kalan fark L
  kanalından/model gölgelemesinden — beklenen), `ColorAuditorContract`/
  `ColorLockContract` DI binding'leri (kapalıyken Null, açıkken Python'a
  düşüyor), `php artisan test --filter=Creative` (95 test) temiz,
  `GeminiTryOnPromptBuilderTest`'e yeni senaryo.

## ❌ Faz R — HR-VITON self-hosted try-on sürücüsü (denendi, İPTAL — repo'dan kaldırıldı, 2026-08-19)
Ücretsiz/self-hosted bir 4. try-on seçeneği denendi: `/home/tryon_model` (bu repo DIŞINDA, ayrı
bir FastAPI servisi) HR-VITON+DensePose çalıştırıyordu — `HrvitonTryOn` sürücüsü bunun
`/training-images` + `/try-on` + `/results/{file}` uçlarını sarmalıyordu, `CreativeServiceProvider`
try-on zincirine `gemini → fal → hrviton → mock` olarak eklenmişti.

**2026-08-18 bulgusu — kullanıcı gözlemi:** "resmi gemini gibi giydirmiyor, saçma şekilde
mankenin üstüne crop yapıyor". Gerçek çıktılar incelendi — bir örnekte kumaşın üstünde **başka
bir çocuk modelin yüzünün bir parçası** warp edilmiş halde görünüyordu. Kök sebep mimari:
HR-VITON, VITON-HD verisiyle eğitilmiş bir **2D TPS/optical-flow warp** motoru (Gemini gibi
generative değil) — "cloth" girdisi olarak flat-lay/ghost-mannequin ürün fotoğrafı bekliyor. Bu
mağazanın ürün kapak fotoğrafları ise modelde giyilmiş (worn photo) olduğundan warp
bloklara/yamalara ayrılıyor; segmentasyon fallback'i bazen yüz pikselini sızdırıyor. Bu HERHANGİ
bir klasik warp-tabanlı try-on modelinde (yalnız HR-VITON'a özgü değil) aynı olurdu; diffüzyon-
tabanlı self-hosted alternatifler de aynı flat-cloth varsayımını taşır VE bu sunucuda GPU yok —
CPU'da pratik değiller. Bir "giysi düzleştirme" (worn-photo → flat-lay, Gemini ile) çözümü
denenip kodlandı ama prod'a hiç açılmadan, **2026-08-19'da HR-VITON kullanılmayacağına karar
verildi** ve tüm ilgili kod repo'dan silindi:
- `Services/Ai/Drivers/Hrviton/HrvitonTryOn.php`
- `Services/Enhancement/{GarmentFlattenerContract,NullGarmentFlattener,GeminiGarmentFlattener}.php`
- `CreativeServiceProvider`'daki hrviton binding'i + `flatten_garment` binding'i
- `config/config.php`'deki `ai.hrviton.*` bloğu
- `ProductOnModelService`'teki flatten adımı + `resolveTryOnDriverName()`'daki 'hrviton' kolu

Try-on zinciri tekrar `gemini → fal → mock`'a döndü. `/home/tryon_model` servisi bu repo
DIŞINDA olduğu için dokunulmadı (istenirse ayrıca kapatılabilir).

## Devam etme talimatı (kendime not)
1. Bu dosyadan sıradaki ⬜ fazı seç.
2. `TaskCreate` ile o fazın adımlarını çıkar, `in_progress` işaretle.
3. Faz bitince: bu dosyada ⬜ → ✅ yap + kısa "yapıldı" özeti ekle, auto-memory
   `project_creative_brandcreative_roadmap` dosyasını güncelle.
4. Doğrulama: mock yol + tinker/araç ile uçtan uca; gerekiyorsa `schema:audit`.
