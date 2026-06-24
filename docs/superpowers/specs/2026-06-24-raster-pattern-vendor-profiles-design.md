# Tasarım: Satıcı-Profilli Kalıp Çıkarımı (Atelier)

**Tarih:** 2026-06-24
**Modül:** `Modules/Atelier`
**Tetikleyen dosya:** `docs/superpowers/plans/salopeta-Ebook-LATZEE_compressed-2.pdf` (Nipnaps "LATZ\*EE")
**İlgili roadmap:** `docs/superpowers/plans/yol_haritasi.md` (§1, §2.1, §2.3, §9.5)

---

## 1. Problem

Atelier kalıp kütüphanesinin PDF→DXF dönüştürücüsü (`Modules/Atelier/python/pdf_dxf_service/converter.py`)
**tek bir satıcıya** (Rusça "kombinезон" vektör kalıbı) göre kalibre edilmiş; sabitleri modül
seviyesinde gömülü:

- Çizgi filtresi yalnızca **siyah** (`(0,0,0)`) ve **≤0.6pt** kalınlık kabul eder.
- Izgara etiketi yalnızca `(satır, sütun)` parantezli biçimi okur.
- Ürün/parça sözlüğü yalnızca Rusça.

LATZEE PDF'i bu varsayımların **üçünü birden** kırar ve mevcut hat onu yanlışlıkla "raster
(taranmış)" sayıp eler. Oysa dosyanın incelenen gerçeği:

| Konu | Mevcut hat (RU prototip) | LATZEE / Nipnaps |
|------|--------------------------|------------------|
| Kalıp sayfaları | s.1+ | **s.31–42** (s.1–30 talimat/fotoğraf) |
| Sayfa boyutu | A4 | A4 (210×297mm) — aynı |
| Çizgi kalınlığı | 0.12pt | **0.72pt** (eşik ≤0.6 hepsini eliyor → 0 segment) |
| Çizgi rengi | sadece siyah | **bedenler renk-kodlu**: siyah/yeşil/cyan/sarı/kırmızı/mavi |
| Izgara etiketi | `(1,4)` | çıplak `1 4` |
| Metin dili | Rusça | Almanca (`Latzhose`, `NipNaps`, beden `50/56…170/176`) |
| Ölçü tablosu | yok | Maßtabelle (OW/TW/HW/SL/KH × beden) |

**Kritik fırsat:** LATZEE'de her beden ayrı renkte çizili. Roadmap §2.1'de "iç içe bedenler
ayrılamaz, Faz 3'e ertelendi" denen kısıt bu satıcıda **yok** — bedenler renkle hâlihazırda
ayrık, dolayısıyla beden-bazlı DXF katmanları mümkün.

**Gerçek ihtiyaç:** tek dosyayı yamamak değil; dönüştürücüyü **satıcı-profili** mimarisine
taşımak. Her satıcının konvansiyonu bir profile yaşar, doğru profil yüklemede otomatik
seçilir, tanınmayan dosya operatör kuyruğuna düşer.

---

## 2. Kapsam ve Kararlar

Brainstorming'de doğrulanan kararlar:

1. **Mimari:** satıcı-profili soyutlaması (tek dosya yaması değil). LATZEE ilk somut profil.
2. **DXF beden ayrımı:** renk→DXF katmanı; tüm bedenler tek dosyada, kayıp yok.
3. **Çıkarım derinliği:** zengin — temel metadata + parça + beden **+ ölçü tablosu (Maßtabelle)**
   + kumaş tüketimi.
4. **Profil seçimi:** otomatik imza tahmini + düşük güven/çakışmada operatör onayı
   (mevcut yeşil/sarı/kırmızı triyajına oturur).

**Kapsam dışı (YAGNI):** parametrik beden türetme (roadmap Faz 3), AI konsept eşleme,
OCR'lı gerçek raster (taranmış) kalıp vektörleştirme — bu tasarım yalnızca **vektör** kalıpların
çok-satıcılı çıkarımını kapsar.

---

## 3. Mimari

### 3.1 Profil-güdümlü dönüştürücü

`converter.py` profil-agnostik bir **motora** indirgenir; satıcıya özgü her şey `profiles/` altına taşınır.

```
Modules/Atelier/python/pdf_dxf_service/
├─ converter.py        # profil-agnostik motor: segment çıkar, karo birleştir, DXF kur
├─ profiles/
│  ├─ __init__.py      # REGISTRY + detect_profile(doc) → (profile, confidence)
│  ├─ base.py          # ExtractionProfile dataclass + yardımcılar
│  ├─ nipnaps.py       # LATZEE/Nipnaps profili (ilk somut profil)
│  └─ ruslan.py        # mevcut RU "kombinезон" parametreleri buraya taşınır
└─ main.py             # FastAPI /probe, /convert (değişmez arayüz)
```

### 3.2 ExtractionProfile sözleşmesi

Bir profil iki şeyi tanımlar:

**A. İmza (signature) — `match(doc) -> float (0..1)`**
Dosyanın bu profile ait olma olasılığını ölçer:
- çizgi renk paletinin profile uyumu,
- kalınlık aralığı uyumu,
- ızgara etiket biçimi (`(r,c)` vs çıplak `r c`),
- dildeki anahtar kelimeler (`NipNaps`, `Latzhose` / Rusça terimler).

**B. Çıkarım parametreleri:**
- `tile_page_filter(page)` — talimat/fotoğraf sayfasını atla, kalıp karosunu seç.
- `line_filter(color, width)` — kabul edilen renk/kalınlık.
- `color_size_map` — renk→beden eşlemesi (renk-kodlu satıcılar için).
- `grid_parser(text)` — sayfadan `(satır, sütun)` çıkar.
- `product_lexicon`, `part_lexicon` — ürün tipi/parça adı normalize.
- `measurement_parser(text)` — ölçü tablosu (varsa).
- `size_parser(text)` — beden aralığı.

### 3.3 Profil tespiti ve triyaj

`detect_profile(doc)` tüm kayıtlı profillerin `match()` skorunu hesaplar, en yükseği seçer:

- Skor **yüksek ve tek başına net** → o profille devam (yeşil aday).
- Skor **eşiğin altında** veya **iki profil yakın** → `error_report.profile_candidates`
  doldurulur, `classification = yellow`; operatör onaylar/seçer.
- **Hiçbir profil tutmaz** veya dosya gerçekten raster → `classification = red`,
  "tanınmayan satıcı / vektör değil".

Motor (karo birleştirme, ölçek doğrulama, DXF kurma) tek yerde kalır; yeni satıcı = yeni
`profiles/*.py`, çekirdek dokunulmaz.

---

## 4. Nipnaps (LATZEE) profili

### 4.1 Karo sayfa tespiti

"Sayfa 31" sabit DEĞİL — imzadan bulunur: bir sayfa, **A4** + **>50 kabul edilen renkli vektör
segment** + **çıplak `satır sütun` ızgara etiketi** taşıyorsa kalıp karosudur; gerisi
(fotoğraf/talimat) atılır. Başka Nipnaps dosyasında kalıp farklı sayfadan başlasa da çalışır.

### 4.2 Çizgi filtresi (mevcut hatayı düzelten kısım)

- Kalınlık eşiği gevşetilir: LATZEE çizgileri **0.72pt** (mevcut ≤0.6 hepsini eliyordu).
- Renk filtresi "sadece siyah" değil; profilin `color_size_map` paletindeki **tüm** renkler kabul.

```python
# Ölçülen RGB (toleranslı eşleme) → beden rolü
COLOR_SIZE_MAP = {
  (0.00, 0.00, 0.00): "ortak",   # siyah = bedenler-arası ortak çizgi/işaret
  (0.00, 0.50, 0.00): "size_a",  # yeşil
  (0.00, 1.00, 1.00): "size_b",  # cyan
  (0.78, 0.78, 0.00): "size_c",  # sarı
  (0.62, 0.00, 0.00): "size_d",  # koyu kırmızı
  (0.12, 0.12, 1.00): "size_e",  # mavi
}
COLOR_TOL = 0.08  # RGB başına tolerans
```

Gerçek beden adları (`50/56`, `116`…) Maßtabelle/Größeneinteilung'dan okunup renk sırasıyla
eşlenir. Eşleşme kurulamazsa renk-etiketi (`size_a`) kalır; operatör doğrular.

### 4.3 Renk→katman DXF

Çekirdek `_build_dxf` segmentleri **rengine göre ayrı DXF layer**'a yazar
(`SIZE_116`, `SIZE_122`, … + `ORTAK`), her layer'a o rengin ACI karşılığı atanır. Tek dosya,
tüm bedenler; kalıpçı CAD'de istediği layer'ı açar/kapar.

### 4.4 Ölçek doğrulaması

Mevcut mantık korunur (roadmap §2.3): karoların A4'e gerçek sapması + karolar-arası tekdüzelik.
`scale_verified` + `scale_deviation_mm` üretilir. (Bu satıcının PDF'inde etiketli kontrol karesi
yoktur; ölçek garantisi A4 + tekdüzeliktir.)

### 4.5 Metadata çıktısı

```json
{
  "profile": "nipnaps",
  "product_type": "tulum",
  "size_range": "50/56-170/176",
  "size_layers": ["116","122","128","134"],
  "color_size_map": { "#00FF00": "116", "#00FFFF": "122", ... },
  "scale_verified": true,
  "scale_deviation_mm": 0.4,
  "grid": { "rows": R, "cols": C },
  "segment_count": N,
  "parts": [ {"part_name": "Ön", "quantity": 2}, ... ],
  "measurements": { ... },     // §5
  "fabric_usage": { ... }      // §5
}
```

---

## 5. Veri Modeli

Roadmap §9.5: aranabilir/filtrelenebilir alanlar kolon; geri kalan yapılandırılmış veri JSON.
Ölçü tablosu ve renk haritası filtre değil → JSON kolon. Yeni tablo gerekmez; tek migration.

### 5.1 Migration

`Modules/Atelier/database/migrations/2026_06_24_xxxxxx_add_extraction_data_to_patterns_table.php`

```php
Schema::table('patterns', function (Blueprint $t) {
    $t->json('size_layers')->nullable()->after('size_range');     // ["116","122",...]
    $t->json('color_size_map')->nullable()->after('size_layers'); // {"#00FF00":"116",...}
    $t->json('measurements')->nullable()->after('color_size_map');// Maßtabelle
    $t->json('fabric_usage')->nullable()->after('measurements');  // beden→kumaş tüketimi
});
// down(): aynı dört kolonu dropColumn ile gerçekten düşürür (CLAUDE.md disiplini).
```

`size_range`, `product_type`, `vendor` zaten mevcut. `vendor` alanına profil adı (`nipnaps`) yazılır.

### 5.2 `measurements` yapısı

Maßtabelle s.4'ten parse (mevcut `pdftotext` metniyle test edildi):

```json
{
  "labels": ["OW","TW","HW","SL","KH"],
  "sizes":  ["56","62","68","74","80","86","92","98","104","110","116","122","128","134","140","146","152","158","164","170","176"],
  "matrix": {
    "OW": [22.5, 23.5, 24.5, ...],
    "TW": [21, 22, 23, ...],
    "HW": [23, 24, 25, ...],
    "SL": [27.5, 31, 34.5, ...],
    "KH": [56, 62, 68, ...]
  },
  "unit": "cm",
  "note": "Modellmasse ohne Nahtzugabe"
}
```

### 5.3 `fabric_usage` yapısı

Stoffverbrauch tablosundan: `{ "50/56": "30cm @150 VB", "62/68": "40cm @150 VB", ... }`.

### 5.4 İlişkili tablolar

- `pattern_parts` — Almanca sözlükten (Ön/Arka/Cep/Askı/Bund…), mevcut tablo.
- `pattern_tags` — satıcı + stil etiketleri (`nipnaps`, `latzhose`, `slimfit`).

---

## 6. Laravel Akışı

Mevcut iskelet (`ConversionPipelineService`, `ConversionController`, `PatternController`,
`ProcessConversionJob`, `PdfDxfConverterContract` + HTTP driver, `Conversions.vue`) korunur;
genişletilir.

```
Yükle (PatternController / ConversionController)
  └─ submit(): PDF sakla + conversion_job (pending)
       └─ ProcessConversionJob (kuyruk)
            └─ ConversionPipelineService::process()
                 └─ PdfDxfConverterContract::convert() → FastAPI /convert
                      └─ detect_profile() → nipnaps → renk-layer DXF + measurements
                 └─ job: classification + confidence + error_report.metadata(JSON) + needs_review
  └─ Operatör onay ekranı (Conversions.vue): PDF ↔ DXF önizleme,
       profil + bulunan bedenler + ölçü tablosu + eksikler görünür
       └─ approve() → Pattern + parts + measurements/size_layers/fabric_usage/color_size_map + tags
       └─ reject()  → iş kapanır, kalıp üretilmez
```

`ConversionPipelineService::approve()` mevcut `parts` döngüsünü korur; üstüne yeni JSON
alanlarını (`measurements`, `size_layers`, `fabric_usage`, `color_size_map`) ve `vendor`'u
(profil adı) map'ler. `conversion_jobs.error_report.metadata` bu JSON'u zaten taşır.

### 6.1 Triyaj kuralları

- **Yeşil:** profil skoru yüksek + ölçek OK + ızgara tam + segment bol + ≥2 beden rengi ayrıştı
  → operatör göz atıp onaylar.
- **Sarı:** profil tuttu ama eksik (parça okunamadı / ızgara delik / renk-beden eşleşmedi /
  profil adayları yakın) → operatör düzeltir/seçer.
- **Kırmızı:** hiçbir profil tutmadı / gerçekten raster / ölçek güvenilmez → reddet ya da elle.

`error_report` operatöre görünür kanıt taşır: hangi profil, kaç karo, hangi renkler bulundu,
ölçek sapması, eksik alanlar.

---

## 7. Test Stratejisi (TDD)

**Python (pytest, `python/pdf_dxf_service/`):**
- `profiles/`: nipnaps imzası LATZEE'yi tanır, RU dosyasını tanımaz; ruslan imzası tersini yapar;
  iki profil yakınsa sarı (çakışma) döner.
- `convert_pdf(LATZEE)`: ≥6 renk-layer'lı DXF üretir, `segment_count > 200`, ölçek doğrulanır,
  `measurements.matrix` dolu, `size_layers` boş değil.
- `measurement_parser` birim testi: gerçek Maßtabelle metni fixture'ı → beklenen matris.
- Regresyon: mevcut RU dosyası hâlâ doğru çıktı verir (ruslan profili).

**Laravel (Pest, `Modules/Atelier/tests`):**
- `approve()` yeni JSON kolonlarını (`measurements`, `size_layers`, `fabric_usage`,
  `color_size_map`) ve `vendor`'u yazar; `pattern_parts`/`pattern_tags` dolar.
- Raster/red dosya `Pattern` ÜRETMEZ (red işi onaya gelmez).
- Sarı iş operatör onayı olmadan Pattern üretmez.

**Fixture:** gerçek `salopeta-Ebook-LATZEE_compressed-2.pdf` golden test dosyası olarak
repoya konur (test sabitleri ona göre kalibre).

---

## 8. Riskler

| Risk | Etki | Azaltma |
|------|------|---------|
| Renk paleti dosyadan dosyaya hafif kayar | Renk→beden yanlış eşleşir | `COLOR_TOL` toleransı + operatör doğrulama |
| Maßtabelle metin düzeni bozuk parse | Ölçü matrisi eksik/kayık | Parse başarısızsa `measurements=null` + sarı; geometri yine de üretilir |
| İki profil imzası çakışır | Yanlış profil | Yakın skorlarda sarıya düş, operatör seçsin |
| Beden adı↔renk sırası belirsiz | Layer adları yanlış | Eşleşmezse `size_a/b/c` etiketi kalır, operatör adlandırır |
| Karo ızgarası delik (eksik sayfa) | Birleştirmede boşluk | `grid_full` kontrolü → sarı + `error_report` |

---

## 9. Uygulama Sırası (özet)

1. `profiles/base.py` + registry + `detect_profile`; mevcut RU sabitlerini `ruslan.py`'ye taşı (testler yeşil kalmalı).
2. `nipnaps.py`: imza + karo filtresi + renk-beden palet + Almanca sözlük + Maßtabelle parser.
3. `converter.py` motorunu profil parametreleriyle çalışacak şekilde refactor; renk→layer DXF.
4. Migration: patterns'a 4 JSON kolon (+`down`).
5. `ConversionPipelineService::approve()` yeni alanları map'ler; `vendor`=profil.
6. `Conversions.vue` onay ekranı: profil/beden/ölçü tablosu/eksikler gösterimi.
7. Testler (pytest + Pest) + LATZEE fixture golden test.
