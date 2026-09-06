"""Genel (marka-bağımsız) vektör kalıp profili.

Hiçbir satıcı sözlüğü/imzası TAŞIMAZ — yalnızca YAPISAL sinyallere bakar:
font-boyutu istatistiği, karo tekdüzeliği, ızgara kümeleme. detect_profile()
REGISTRY'sinde YER ALMAZ (match() her zaman 0 döner, otomatik seçilmez);
converter.convert_pdf() bilinen HİÇBİR satıcı profili tutmadığında bu profili
AÇIKÇA dener — yalnızca o da ızgara kuramazsa ham sayfa-yanyana dökümüne
(vectorize_vector_generic) düşülür. Amaç: "hangi marka" önemli değil, HER PDF
kendi içindeki montaj ipuçlarından incelenip mümkünse gerçekten BİRLEŞTİRİLMİŞ
bir DXF üretmeli (bkz. Modules/Atelier/python/pdf_dxf_service/profiles/burda.py
docblock'u — bu profil Burda'da bulunan "dolaylı ızgara" tekniğinin marka
sözlüğünden arındırılmış genellemesidir).

İki bağımsız strateji sırayla denenir (mevcut motor — _collect_tiles/_assemble_grid
— değişmeden, yalnızca bu profil üzerinden):
  1) DOĞRUDAN IZGARA: karo kendi sayfasında "(satır,sütun)" gibi parantezli bir
     çift taşıyor mu — bu, tek bir satıcıya değil, yaygın bir CAD-montaj
     kuralına karşılık gelir (Ruslan'da da aynı biçim görülür, ama biçim
     kendisi vendor-özel değildir).
  2) DOLAYLI IZGARA (Burda'da gözlenen tekniğin genellemesi): karo sayfasında
     TEK BAŞINA duran, o sayfanın kendi gövde-metin boyutunun İSTATİSTİKSEL
     olarak ÇOK ÜZERİNDE bir 1-2 haneli seri numarası var mı; ayrı bir "genel
     bakış" sayfasında AYNI numaralar KÜÇÜK ve TEKDÜZE boyutta, tekrarsız,
     ızgara düzeninde bulunuyor mu. Sabit pt eşiği YOK — sayfanın kendi
     font dağılımına göre bağıl (oransal) karar verilir.

Hiçbiri kurulamazsa own_tile_label/build_grid_map None döner; çağıran taraf
(converter.convert_pdf) ham sayfa dökümüne düşer.
"""

from __future__ import annotations

import re
from statistics import median

from .base import ExtractionProfile, hue_role

MAX_WIDTH_PT = 2.0

DIRECT_GRID_RE = re.compile(r"\(\s*(\d{1,2})\s*[,;]\s*(\d{1,2})\s*\)")
NUM_RE = re.compile(r"^\d{1,2}$")

OUTLIER_RATIO = 3.0       # own tile label >= gövde-metninin bu katı büyüklükte
MIN_OUTLIER_SIZE = 40.0   # ...ve mutlak olarak da "büyük" (gövde metniyle karışmasın)
BODY_CAP_PT = 30.0        # gövde-medyanı hesabına yalnızca bunun ALTINDAKİ span'lar girer
                          # (aday dev etiketin KENDİSİ medyanı şişirip kendi eşiğini
                          # yükseltmesin diye — az metinli sayfalarda kritik, bkz. test)
OVERVIEW_MIN_LABELS = 6   # bir sayfayı "genel bakış ızgarası" saymak için asgari etiket
OVERVIEW_SIZE_TOL = 2.0   # genel bakış etiketleri boyutça tekdüze olmalı (satır no. karışmasın)
ROW_Y_TOL = 6.0           # aynı ızgara satırı sayılacak y-yakınlığı (pt)


def _all_spans(page) -> list:
    """[(metin, punto, x, y), ...] — sayfadaki TÜM metin span'ları (tek get_text('dict') çağrısı)."""
    out = []
    for block in page.get_text("dict")["blocks"]:
        for line in block.get("lines", []):
            for span in line["spans"]:
                out.append((span["text"].strip(), span["size"], span["origin"][0], round(span["origin"][1])))
    return out


def _numeric_spans(spans: list) -> list:
    """_all_spans() çıktısından yalnızca 1-2 haneli "salt sayı" olanlar."""
    return [s for s in spans if NUM_RE.fullmatch(s[0])]


def _body_median_size(spans: list) -> float:
    """Gövde-metin medyanı — BODY_CAP_PT üstü (aday dev etiket adayları) hariç tutulur."""
    sizes = [sz for _t, sz, _x, _y in spans if sz < BODY_CAP_PT]
    if not sizes:
        sizes = [sz for _t, sz, _x, _y in spans]
    return median(sizes) if sizes else 10.0


class GenericGridProfile(ExtractionProfile):
    name = "generic_grid"
    assembly = "grid"
    expected_tile_mm = None  # karo boyutu a priori bilinmiyor; yalnızca tekdüzelik kontrol edilir

    def accept_line(self, color, width) -> bool:
        if not color:
            return False
        w = width or 0.0
        return not (w and w > MAX_WIDTH_PT)

    def color_role(self, color):
        return hue_role(color)

    def parse_grid(self, text: str):
        m = DIRECT_GRID_RE.search(text.replace("\n", " "))
        return (int(m.group(1)), int(m.group(2))) if m else None

    def own_tile_label(self, page) -> str | None:
        spans = _all_spans(page)
        if not spans:
            return None
        body = _body_median_size(spans)
        threshold = max(MIN_OUTLIER_SIZE, body * OUTLIER_RATIO)
        big = [t for t, sz, _x, _y in _numeric_spans(spans) if sz >= threshold]
        # Tam olarak BİR aykırı büyük numara olmalı — birden fazla varsa bu
        # sayfa bir ölçü tablosu/başlık olabilir, karo seri numarası değil.
        return big[0] if len(big) == 1 else None

    def build_grid_map(self, doc) -> dict | None:
        best_page, best_spans = None, []
        for i in range(doc.page_count):
            spans = _numeric_spans(_all_spans(doc[i]))
            if len(spans) < OVERVIEW_MIN_LABELS:
                continue
            sizes = [sz for _t, sz, _x, _y in spans]
            if max(sizes) - min(sizes) > OVERVIEW_SIZE_TOL:
                continue  # boyutça tekdüze değilse "genel bakış ızgarası" değildir
            labels = [t for t, *_ in spans]
            if len(set(labels)) != len(labels):
                continue  # tekrarlı etiket → ölçü tablosu vb., ızgara etiketi değil
            if len(spans) > len(best_spans):
                best_page, best_spans = i, spans
        if best_page is None:
            return None

        rows: list = []
        for label, _sz, x, y in sorted(best_spans, key=lambda s: s[3]):
            if rows and abs(y - rows[-1][0]) <= ROW_Y_TOL:
                rows[-1][1].append((label, x))
            else:
                rows.append([y, [(label, x)]])

        grid: dict = {}
        for r, (_y, items) in enumerate(rows, start=1):
            for c, (label, _x) in enumerate(sorted(items, key=lambda t: t[1]), start=1):
                grid[label] = (r, c)
        return grid

    def parse_product(self, text: str):
        return None  # marka sözlüğü yok — AI pilot (PatternMetadataExtractor) tamamlar

    def parse_size(self, text: str):
        return None

    def parse_parts(self, text: str) -> list:
        return []

    def match(self, doc) -> float:
        return 0.0  # REGISTRY skorlamasına katılmaz; yalnızca açık fallback'ten çağrılır
