# Atelier — Raster Kalıp Sayısallaştırma (İnsan-Destekli İzleme)

**Tarih:** 2026-06-25
**Modül:** `Modules/Atelier`
**Durum:** Tasarım onaylandı (uygulama bekliyor)

## 1. Problem

Atelier'in PDF→DXF çıkarım hattı yalnızca **vektör** kalıp PDF'leriyle çalışır. Kullanıcının
kaynaklarının çoğu ise **raster (taranmış) dergi kalıplarıdır** (ör. ШиК, Burda) — "üst üste
master tabaka" yapısında: birçok parça ve tüm bedenler aynı sayfada iç içe, bedenler çoğunlukla
renkle değil çizgi stiliyle ayrılır. Bu yapının **tam otomatik** raster→vektör çıkarımı güvenilir
değildir (insanlar bunları elle, aydınger kâğıdıyla kopyalar).

Mevcut davranış: raster PDF yüklenince converter `red` döner ve kalıp `extraction_status=failed`
olur ("Tanınan satıcı profili yok"). Sonuç: bu kalıplar sisteme hiç giremez.

## 2. Hedef ve başarı kriteri

Raster bir kalıp sayfasını **insan-destekli (yarı otomatik)** sayısallaştırmak: taranmış sayfayı
web tuvalinde arka plana koymak, kullanıcının ölçeği kalibre edip istediği parçayı/bedeni
çizmesini sağlamak, çizimi **mm cinsinden DXF**'e çevirip mevcut kalıp kütüphanesine kaydetmek.

**Başarı:** Kullanıcı bir raster kalıbı yükleyip "Sayısallaştır" ile bir parçayı çizebiliyor;
üretilen DXF bir CAD'de doğru ölçüyle açılıyor; kalıp bundan sonra vektör-çıkarımlı bir kalıpla
**aynı** davranıyor (downstream üretim akışı değişmiyor).

## 3. Seçilen yaklaşım

**A — Tarayıcı-tuvali destekli izleyici.** İzleme web tuvalinde (Vue) yapılır; Python yalnızca
(a) PDF sayfasını PNG'ye render eder, (b) mümkünse ölçek referansını tespit eder, (c) izlenen
poligonları DXF'e yazar. Mevcut yığın (Pattern modeli, kütüphane, `_build_dxf`, PyMuPDF, izinler)
yeniden kullanılır.

Reddedilen alternatifler:
- **B (Python otomatik çizgi-izleme + elle temizlik):** master tabakalarda kontürler iç içe/gürültülü;
  çok fazla temizlik, belirsiz kazanç.
- **C (dış araç gidiş-dönüş, Inkscape/CAD):** en az geliştirme ama en kötü deneyim, uygulamadan çıkıyor.

## 4. Mimari ve bileşenler

### Yeni bileşenler
1. **`RasterTracer.vue`** (`Modules/Atelier/Resources/assets/js/Pages/`): taranmış görüntü
   backdrop'u + zoom/pan, kalibrasyon aracı, poligon çizim aracı, parça/beden/grainline etiketleme,
   kaydet. Mevcut `AppLayout` + `AtelierNav` desenini izler.
2. **Python servisine (`Modules/Atelier/python/pdf_dxf_service`) 3 endpoint:**
   - `POST /render` — multipart `file` (PDF) + `page` (int) + `dpi` (varsayılan 200) → PNG
     (`fitz.get_pixmap`). Yanıt: PNG bytes veya base64 + `width`/`height` (px).
   - `POST /build-dxf` — JSON: mm cinsinden poligonlar `[{role, points:[[x,y]...]}]` →
     DXF string. Mevcut `_build_dxf(lines, texts)` yeniden kullanılır (poligon → ardışık `lines`).
   - `POST /detect-scale` *(best-effort)* — `file` + `page` → kontrol karesi/cetvel bulursa
     `{px_per_mm}` önerir; bulamazsa `{}`. Bulunamaması akışı engellemez (manuel kalibrasyona düşülür).
3. **`PatternController` metodları:**
   - `tracer(Pattern $pattern)` → Inertia `RasterTracer` sayfası (sayfa sayısı + render URL'leri).
   - `tracerImage(Pattern $pattern, int $page)` → Python `/render` proxy'si (PNG döndürür).
   - `saveTraced(SaveTracedPatternRequest $request, Pattern $pattern)` → çizimi al, px→mm çevir,
     Python `/build-dxf` çağır, `applyTracedDxf` ile kaydet, Patterns'a redirect.
4. **`PatternLibraryService::applyTracedDxf(Pattern, string $dxf, array $meta, array $parts)`** —
   DXF'i saklar, parçaları `syncParts` ile yazar, metadata + `scale_verified=true` +
   `extraction_status=done` set eder. Mevcut `deleteFile`/`syncParts` desenini kullanır.
5. **`PatternLibraryService::markNeedsTracing(Pattern)`** — `extraction_status=needs_tracing`.
6. **`SaveTracedPatternRequest`** (FormRequest) — kalibrasyon + poligon doğrulaması.

### Yeniden kullanılan (yeni iş yok)
- `Pattern` modeli ve `patterns` tablosu — **yeni alan/migration gerekmez**.
- DXF depolama/kütüphane akışı, `_build_dxf` (ezdxf, MM birimi, katmanlar), PyMuPDF.
- `can:atelier.pattern.manage` izni, Patterns listesi ekranı.

### Yeni durum sabiti
`Pattern::EXTRACTION_NEEDS_TRACING = 'needs_tracing'`. `extraction_status` varchar olduğundan
migration gerekmez. (Proje DB disiplini: şema şişmesi yok.)

### Yeni route'lar (`Modules/Atelier/routes/web.php`, mevcut patterns grubu içine)
```
GET    patterns/{pattern}/tracer         → tracer        (can:atelier.pattern.manage)
GET    patterns/{pattern}/tracer/{page}  → tracerImage   (can:atelier.pattern.manage)
POST   patterns/{pattern}/traced         → saveTraced    (can:atelier.pattern.manage)
```

## 5. Veri akışı

1. **Yükleme → yönlendirme:** Mevcut `createPdfDraft` + `ExtractPatternFromPdfJob` çalışır.
   Python `/convert` yanıtına `kind` (vector/raster) eklenir; `ConversionResult` taşır.
   `applyExtraction`: sonuç `red` **ve** `kind=raster` ise `markNeedsTracing`, aksi halde
   (gerçekten bozuk/boş) `markExtractionFailed`.
2. **Editörü açma:** Patterns listesinde `needs_tracing` kalıpta "Sayısallaştır" butonu →
   `GET .../tracer` → `RasterTracer.vue`.
3. **Tuval içi:**
   - Backdrop: her sayfa `tracerImage` → PNG (200 DPI). Zoom/pan.
   - Kalibrasyon: kullanıcı bilinen mesafeyi çizer + mm girer → `px_per_mm`. (Açılışta
     `/detect-scale` önerisi varsa ön-doldurulur.)
   - İzleme: noktalarla kapalı kesim konturu (katman `cut`); opsiyonel grainline (`grain`),
     pens/işaret. Her parçaya ad + adet + beden. Tek oturumda birden çok parça.
4. **Kaydetme → DXF → kütüphane:** Editör JSON POST →
   `{ calibration:{px_per_mm}, pieces:[{name, quantity, size, polylines:[{role, points(px)}]}] }`.
   Controller px→mm (DXF y-yukarı: `y_mm = (img_h - y_px)/px_per_mm`), Python `/build-dxf` → DXF →
   `applyTracedDxf`. Kalıp `done` olur ve downstream'de vektör kalıbı gibi davranır.

### Koordinat dönüşümü
Editör piksel koordinatında çalışır. `mm = px / px_per_mm`. DXF y-yukarı için
`y_mm = (img_height_px - y_px) / px_per_mm`. Köken sol-alt. Çok sayfa: her sayfa kendi
kalibrasyonuyla bağımsız.

## 6. Hata yönetimi ve kenar durumları

- **Python servisi kapalı:** `/render`, `/build-dxf` `Http::timeout` ile sarılır; başarısızsa
  controller net mesaj döner ("PDF→DXF servisi çalışmıyor; başlatın"). Editör backdrop yüklenemezse
  "tekrar dene" gösterir, kalıbı bozmaz.
- **Kalibrasyonsuz/eksik geometri:** `SaveTracedPatternRequest` `px_per_mm > 0` ve ≥1 kapalı
  poligon (≥3 nokta) ister; eksikse 422, kalıp `needs_tracing` kalır.
- **Geçersiz geometri:** kendiyle kesişen/dejenere poligon Python `/build-dxf`'te yakalanır,
  hata döner, kayıt yapılmaz.
- **Yarıda bırakma:** kaydetmeden çıkış kalıcı veri yazmaz; kalıp `needs_tracing` kalır.
  (İzleme oturumu taslağı v1'de yok — YAGNI.)
- **İdempotentlik:** `applyTracedDxf` eski `dxf_path`'i siler, parçaları tam değiştirir → tekrar
  kaydetme güvenli.
- **Çok sayfalı tabaka:** v1 = sayfa-başına izleme. Parça birden çok sayfaya yayılırsa v1 sınırı,
  UI'da not. Otomatik birleştirme sonraki faz.
- **`/detect-scale` yanlış öneri:** kullanıcı üzerine yazar (öneri sadece ön-doldurma).
- **İzin:** tüm uçlar `can:atelier.pattern.manage`.

## 7. Test stratejisi

**Python (pytest, `test_converter.py` yanında):**
- `/build-dxf`: bilinen mm poligon → doğru koordinat/katman, y-flip doğruluğu, kapalı kontur.
- `/render`: örnek PDF → beklenen boyutta PNG, doğru sayfa sayısı.
- Dejenere poligon → hata döner, çökmez.

**Laravel (mevcut Atelier test deseni, Python mock'lu):**
- `applyTracedDxf`: DXF + parçalar yazılır, `extraction_status=done`, eski dosya silinir.
- `markNeedsTracing` ayrımı: raster `kind` → `needs_tracing`; bozuk PDF → `failed`.
- `SaveTracedPatternRequest`: kalibrasyon/poligon eksik → 422.
- Yetki: `pattern.manage` yoksa 403.

**Manuel kabul:** gerçek ШиК taraması → sayısallaştır → DXF'i CAD'de aç, ölçü doğrulaması.

## 8. Kapsam dışı (v1 değil, bilinçli)

Otomatik sayfa-birleştirme (stitching), yarı-otomatik kenar yapışma (B yaklaşımı), izleme oturumu
taslağı, beden gradasyonu, otomatik parça tanıma.
