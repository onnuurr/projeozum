# Atelier PDF→DXF Mikroservisi (Kol 1)

Kalıp PDF'lerini DXF'e çeviren ayrı Python servisi. Laravel kuyruk işçisi
(`ProcessConversionJob`) bu servise PDF gönderir; servis DXF + sınıflandırma +
metadata döndürür. Onay her zaman insandadır (yol haritası §2.4).

## Çalıştırma

```bash
cd Modules/Atelier/python/pdf_dxf_service
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8200
```

veya Docker ile:

```bash
docker build -t atelier-pdfdxf .
docker run -p 8200:8200 atelier-pdfdxf
```

## Laravel bağlantısı (.env)

```
ATELIER_CONVERSION_DRIVER=http
ATELIER_CONVERSION_URL=http://127.0.0.1:8200
```

Servis kapalıyken veya `ATELIER_CONVERSION_DRIVER=mock` iken Laravel sahte
dönüştürücüye düşer (`MockPdfDxfConverter`) — servissiz geliştirme/test için.

## API

`POST /convert` — multipart, alan adı `file` (PDF).

Yanıt:

```json
{
  "classification": "green|yellow|red",
  "confidence": 0-100,
  "dxf_base64": "<base64 DXF | null>",
  "metadata": {
    "name": "...", "product_type": null, "size_range": null,
    "scale_verified": true, "scale_deviation_mm": 0.3,
    "grid": {"rows": 5, "cols": 5}, "segment_count": 512,
    "parts": [{"part_name": "Kol", "quantity": 1, "occurrences": 4}]
  },
  "errors": []
}
```

`GET /health` — `{"status": "ok"}`.

`POST /probe` — multipart `file` (PDF). Hızlı vektör/raster ön-kontrolü (DXF üretmez).
Yükleme anında çağrılır; raster/taranmış dosyayı baştan eler. Yanıt:

```json
{ "kind": "vector_tiled|vector|raster|empty|invalid",
  "pages": 26, "vector_pages": 9, "image_pages": 26, "has_grid": true }
```

Laravel `PatternController@importPdf` bu sonuca göre `raster`/`empty`/`invalid`
dosyaları reddeder, vektör olanları taslağa alır. Servis kapalıysa probe `null`
döner → ön-kontrol atlanır, akış engellenmez (asenkron job yine de yakalar).

## Gerçek örnekle doğrulandı (2026-06-24)

26 sayfalık gerçek bir kalıp PDF'i (komбинезон, beden 116-134) ile uçtan uca test edildi:

| Sinyal | Sonuç |
|--------|-------|
| Sayfa 0 | Letter kapak → atlandı |
| Sayfa 1-25 | A4 karo, 5×5 ızgara, köşede `(satır, sütun)` |
| Renk filtresi | stroke `(0,0,0)` @0.12pt tutuldu; `None` watermark atıldı |
| Birleştirme | 25 karo → tek DXF, bbox 1048×1485mm |
| Segment | **512** temiz çizgi (roadmap'teki sayıyla birebir) |
| Ürün tipi | `комбинезон` → `tulum` |
| Beden | `р.116-134` → `116-134` |
| Sınıflandırma | green / güven 99 |

Sentetik regresyon testi: `python test_converter.py` (gerçek PDF gerektirmez).
İnceleme araçları: `inspect_grid.py <pdf>` (sayfa/ızgara/etiket dökümü).

## Hâlâ açık (bu örnekte gözlendi)

- **Ayrı parça etiketi yok**: bu satıcının PDF'inde Рукав/Капюшон gibi parça
  adları metin olarak yok → `parts` boş döner, operatör doldurur. Farklı
  satıcıda `PART_LEXICON` genişletilebilir.
- **Kontrol karesi (§2.3)**: bu satıcının PDF'inde ETİKETLİ 10×10cm kare YOK
  (kalibrasyon anahtar sözcüğü/kare bulunamadı). DXF doğrudan vektör
  koordinatından üretildiği için ölçek garantisi = karoların A4'e GERÇEK
  sapması (`scale_deviation_mm`, ölçülen) + karo TEKDÜZELİĞİ. Sapma > 3mm veya
  karolar farklı boyutsa `scale_verified=false` + sarı triyaj. (Etiketli kare
  taşıyan satıcı çıkarsa o kareyi ölçen ek bir adım eklenebilir.)
- **İç içe bedenler tek katmanda** çıkar — beden ayrımı Faz 3 Ar-Ge (§2.1).
- Farklı renk şeması/şablon gelirse sarı/kırmızı triyaja düşer (eşikler tek
  satıcıya göre).
