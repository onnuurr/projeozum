# Satıcı-Profilli Kalıp Çıkarımı Uygulama Planı

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Atelier kalıp dönüştürücüsünü tek satıcıya gömülü yapıdan, yüklemede doğru satıcı profilini otomatik seçen, renk-kodlu bedenleri DXF katmanlarına ayıran ve zengin metadata (parça/ölçü/kumaş) çıkaran çok-satıcılı bir hatta dönüştürmek.

**Architecture:** Python `converter.py` profil-agnostik bir motora indirgenir; satıcıya özgü her şey `profiles/*.py` altındaki `ExtractionProfile` sınıflarına taşınır. Bir profil hem bir **imza** (`match(doc)→0..1`) hem **çıkarım parametreleri** taşır. Motor profili tespit eder, karoları profilin `assembly` stratejisine göre (Rusça=grid stitch, Nipnaps=kontak-sayfası) yerleştirir, segmentleri renk-rolüne göre ayrı DXF katmanına yazar. Laravel tarafı (`ConversionPipelineService`) çıkan zengin metadata'yı onay anında `patterns` tablosunun yeni JSON kolonlarına map'ler.

**Tech Stack:** Python 3 + PyMuPDF (`fitz`) + ezdxf + FastAPI (mevcut mikroservis); Laravel 11 + Pest; Vue 3 (Inertia).

## Global Constraints

- **PostgreSQL** veritabanı; her migration'ın `down()`'u gerçek ters işlemi yapar (CLAUDE.md §3). Kolon kaldırma `dropColumn` ile.
- **Geometri dosyada, VT'de değil** (roadmap §9.5): DXF dosyada; `patterns`'a yalnızca aranabilir/yapılandırılmış metadata JSON olarak girer.
- **Otomatik onay YOK** (roadmap §2.4): sınıflandırma yalnızca operatöre triyaj rengi verir; `Pattern` kaydı yalnızca operatör `approve()` ile üretilir.
- **Mevcut Rusça regresyonu korunur:** `test_converter.py`'deki sentetik grid testleri (segment_count, grid metadata, yeşil/sarı triyaj) refactor sonrası aynen geçmeli.
- **Python servisi çalıştırma:** `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_converter.py test_profiles.py -v`. Bağımlılıklar `requirements.txt` (PyMuPDF, ezdxf, fastapi).
- **Ölçek doğrulaması** (roadmap §2.3): grid karoların A4'e sapması + tekdüzeliği `scale_verified` + `scale_deviation_mm` üretir.
- **Renk→beden eşlemesi belirsizdir:** DXF katmanları renk-rolüyle adlandırılır (`BEDEN_YESIL`…); gerçek beden adı ataması operatöre bırakılır (asla uydurulmaz).
- **Birleştirme ertelendi:** Nipnaps karoları tek koordinata otomatik montaj edilmez; her karo kontak-sayfası düzeninde, kenar-eşleştirme etiketleriyle birlikte tek DXF'e konur.

**Fixture:** Gerçek test dosyası repoda mevcut: `docs/superpowers/plans/salopeta-Ebook-LATZEE_compressed-2.pdf` (42 sayfa; kalıp karoları s.31–42).

---

## Dosya Yapısı

```
Modules/Atelier/python/pdf_dxf_service/
├─ converter.py            # MODIFY: profil-agnostik motor (convert_pdf, probe_pdf)
├─ profiles/
│  ├─ __init__.py          # CREATE: REGISTRY + detect_profile()
│  ├─ base.py              # CREATE: ExtractionProfile + ortak yardımcılar
│  ├─ ruslan.py            # CREATE: mevcut RU "kombinезон" mantığı (grid)
│  └─ nipnaps.py           # CREATE: LATZEE/Nipnaps profili (contact_sheet)
├─ test_converter.py       # MODIFY: motor refactor'a uyacak şekilde
└─ test_profiles.py        # CREATE: profil imza + ayrıştırıcı testleri

Modules/Atelier/
├─ database/migrations/2026_06_24_150000_add_extraction_data_to_patterns_table.php  # CREATE
├─ Models/Pattern.php       # MODIFY: fillable + casts
├─ Services/Conversion/ConversionPipelineService.php  # MODIFY: approve() map
├─ Resources/assets/js/Pages/Conversions.vue          # MODIFY: çıkarım gösterimi
└─ tests/Feature/ConversionApprovalTest.php           # CREATE: approve() map testi
```

---

### Task 1: Profil temeli (`profiles/base.py`)

**Files:**
- Create: `Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py` (boş, paket işareti — Task 4'te doldurulur)
- Create: `Modules/Atelier/python/pdf_dxf_service/profiles/base.py`
- Test: `Modules/Atelier/python/pdf_dxf_service/test_profiles.py`

**Interfaces:**
- Produces:
  - Sabitler: `PT_TO_MM: float`, `A4_W_MM=210.0`, `A4_H_MM=297.0`, `A4_TOL_MM=3.0`
  - `color_matches(color, target: tuple[float,float,float], tol: float) -> bool`
  - `scan_lexicon(text: str, lexicon: dict[str,str]) -> str | None`
  - `is_a4(page) -> bool`
  - `class ExtractionProfile` (taban): sınıf öznitelikleri `name: str = "base"`, `assembly: str = "grid"`; metotlar `match(doc)->float`, `is_tile_page(page)->bool`, `accept_line(color, width)->bool`, `color_role(color)->str|None` (varsayılan `"ortak"`), `parse_grid(text)->tuple[int,int]|None`, `tile_labels(page)->list[str]`, `parse_product(text)->str|None`, `parse_parts(text)->list[dict]`, `parse_size(text)->str|None`, `parse_measurements(doc)->dict|None`.

- [ ] **Step 1: Write the failing test**

`Modules/Atelier/python/pdf_dxf_service/test_profiles.py`:
```python
from profiles.base import color_matches, scan_lexicon, ExtractionProfile


def test_color_matches_within_tolerance():
    assert color_matches((0.0, 0.5, 0.0), (0.0, 0.5, 0.0), 0.08) is True
    assert color_matches((0.05, 0.52, 0.02), (0.0, 0.5, 0.0), 0.08) is True
    assert color_matches((0.0, 0.0, 0.0), (0.0, 0.5, 0.0), 0.08) is False
    assert color_matches(None, (0.0, 0.5, 0.0), 0.08) is False


def test_scan_lexicon_finds_first_key():
    lex = {"latzhose": "tulum", "jacke": "ceket"}
    assert scan_lexicon("eine coole latzhose", lex) == "tulum"
    assert scan_lexicon("nichts hier", lex) is None


def test_base_profile_defaults():
    p = ExtractionProfile()
    assert p.name == "base"
    assert p.assembly == "grid"
    assert p.color_role((0.1, 0.1, 0.1)) == "ortak"
    assert p.parse_grid("anything") is None
    assert p.parse_parts("anything") == []
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_profiles.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'profiles'`

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py`:
```python
# profiles paketi — registry Task 4'te eklenir.
```

`Modules/Atelier/python/pdf_dxf_service/profiles/base.py`:
```python
"""Satıcı çıkarım profili tabanı ve ortak yardımcılar.

Bir profil iki şey taşır:
  - İMZA: match(doc) -> 0..1, dosyanın bu satıcıya ait olma olasılığı.
  - ÇIKARIM PARAMETRELERİ: karo tespiti, çizgi/renk filtresi, ızgara/etiket,
    sözlükler, beden/ölçü ayrıştırma.
Motor (converter.py) profile bağımsızdır; satıcıya özgü her şey buradadır.
"""

from __future__ import annotations

PT_TO_MM = 25.4 / 72.0
A4_W_MM, A4_H_MM, A4_TOL_MM = 210.0, 297.0, 3.0


def color_matches(color, target, tol: float) -> bool:
    """color (RGB 0..1) target'a her kanalda <= tol içinde mi?"""
    if not color:
        return False
    try:
        return all(abs(float(c) - t) <= tol for c, t in zip(color[:3], target))
    except (TypeError, ValueError):
        return False


def scan_lexicon(text: str, lexicon: dict) -> str | None:
    for key, val in lexicon.items():
        if key in text:
            return val
    return None


def is_a4(page) -> bool:
    w, h = page.rect.width * PT_TO_MM, page.rect.height * PT_TO_MM
    return abs(w - A4_W_MM) <= A4_TOL_MM and abs(h - A4_H_MM) <= A4_TOL_MM


class ExtractionProfile:
    """Tüm profillerin tabanı. Alt sınıflar gerekli metotları ezer."""

    name: str = "base"
    assembly: str = "grid"  # "grid" | "contact_sheet"

    def match(self, doc) -> float:
        return 0.0

    def is_tile_page(self, page) -> bool:
        return False

    def accept_line(self, color, width) -> bool:
        return False

    def color_role(self, color) -> str | None:
        return "ortak"

    def parse_grid(self, text: str):
        return None

    def tile_labels(self, page) -> list:
        return []

    def parse_product(self, text: str):
        return None

    def parse_parts(self, text: str) -> list:
        return []

    def parse_size(self, text: str):
        return None

    def parse_measurements(self, doc):
        return None
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_profiles.py -v`
Expected: PASS (3 test)

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py \
        Modules/Atelier/python/pdf_dxf_service/profiles/base.py \
        Modules/Atelier/python/pdf_dxf_service/test_profiles.py
git commit -m "feat(atelier): ExtractionProfile tabanı + ortak yardımcılar"
```

---

### Task 2: Rusça profili (`profiles/ruslan.py`)

Mevcut `converter.py`'deki RU "kombinезон" mantığını bir profile taşır. Davranış birebir korunur (sentetik regresyon testleri Task 5'te bu profille geçecek).

**Files:**
- Create: `Modules/Atelier/python/pdf_dxf_service/profiles/ruslan.py`
- Test: `Modules/Atelier/python/pdf_dxf_service/test_profiles.py` (mevcut dosyaya ekleme)

**Interfaces:**
- Consumes: `profiles.base` (ExtractionProfile, color_matches, scan_lexicon, is_a4, PT_TO_MM)
- Produces: `class RuslanProfile(ExtractionProfile)` — `name="ruslan"`, `assembly="grid"`. `accept_line`: siyah (≤0.18) ve `width<=0.6`. `color_role`: her zaman `"ortak"`. `parse_grid`: `(\d+,\d+)` parantezli. `parse_product`/`parse_parts`/`parse_size`: RU sözlükleri. `match(doc)`: parantezli ızgara + siyah-ince çizgi varsa yüksek.

- [ ] **Step 1: Write the failing test**

`test_profiles.py` dosyasına ekle:
```python
import fitz
from profiles.ruslan import RuslanProfile

A4_W_PT, A4_H_PT = 210.0 / 0.352777, 297.0 / 0.352777


def _ruslan_doc():
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # kapak
    for r in range(1, 3):
        for c in range(1, 3):
            page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
            page.insert_text((40, 40), f"({r}, {c})")
            for k in range(15):
                page.draw_line((60, 100 + k * 30), (300, 100 + k * 30),
                               color=(0, 0, 0), width=0.12)
    return doc


def test_ruslan_matches_paren_grid_black():
    assert RuslanProfile().match(_ruslan_doc()) >= 0.5


def test_ruslan_accept_line_black_thin_only():
    p = RuslanProfile()
    assert p.accept_line((0, 0, 0), 0.12) is True
    assert p.accept_line((0, 0, 0), 0.72) is False        # kalın → ret
    assert p.accept_line((0, 0.5, 0), 0.12) is False       # yeşil → ret
    assert p.color_role((0, 0, 0)) == "ortak"


def test_ruslan_parse_grid_and_size():
    p = RuslanProfile()
    assert p.parse_grid("köşe (2, 3) etiket") == (2, 3)
    assert p.parse_size("tulum p.116-134") == "116-134"
    assert p.parse_product("tulum modeli") == "tulum"
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m pytest test_profiles.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'profiles.ruslan'`

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/python/pdf_dxf_service/profiles/ruslan.py`:
```python
"""Rusça tek-satıcı vektör kalıbı ("kombинезон"). Mevcut converter mantığı.

Kalıp: siyah (0,0,0) ~0.12pt çizgiler, "(satır, sütun)" parantezli ızgara
etiketi, A4 karolar. Bedenler renksiz iç içe (tek katman). assembly=grid.
"""

from __future__ import annotations

import re

from .base import ExtractionProfile, color_matches, is_a4, scan_lexicon

BLACK = (0.0, 0.0, 0.0)
BLACK_TOL = 0.18
MAX_WIDTH_PT = 0.6

COORD_RE = re.compile(r"\((\d+)\s*,\s*(\d+)\)")
SIZE_RE = re.compile(r"[рp]\.?\s*(\d+)\s*[-–]\s*(\d+)", re.IGNORECASE)

PRODUCT_LEXICON = {
    "комбинезон": "tulum", "tulum": "tulum",
    "куртка": "ceket", "ceket": "ceket", "jacket": "ceket",
    "брюки": "pantolon", "pantolon": "pantolon",
    "платье": "elbise", "elbise": "elbise", "dress": "elbise",
    "толстовка": "kapüşonlu", "kapüşonlu": "kapüşonlu", "hoodie": "kapüşonlu",
    "футболка": "tişört", "tişört": "tişört",
}
PART_LEXICON = {
    "рукав": "Kol", "kol": "Kol",
    "капюшон": "Kapüşon", "kapüşon": "Kapüşon",
    "воротник": "Yaka", "yaka": "Yaka",
    "перед": "Ön", "ön": "Ön", "перёд": "Ön",
    "спинка": "Arka", "arka": "Arka",
    "манжета": "Manşet", "манжет": "Manşet", "manşet": "Manşet",
    "карман": "Cep", "cep": "Cep",
}


class RuslanProfile(ExtractionProfile):
    name = "ruslan"
    assembly = "grid"

    def accept_line(self, color, width) -> bool:
        if not color_matches(color, BLACK, BLACK_TOL):
            return False
        w = width or 0.0
        return not (w and w > MAX_WIDTH_PT)

    def color_role(self, color):
        return "ortak"

    def parse_grid(self, text: str):
        m = COORD_RE.search(text.replace("\n", " "))
        return (int(m.group(1)), int(m.group(2))) if m else None

    def parse_product(self, text: str):
        return scan_lexicon(text, PRODUCT_LEXICON)

    def parse_parts(self, text: str) -> list:
        found: dict = {}
        for key, name in PART_LEXICON.items():
            if key in text:
                found[name] = found.get(name, 0) + 1
        return [{"part_name": n, "quantity": 1} for n in found]

    def parse_size(self, text: str):
        m = SIZE_RE.search(text)
        return f"{m.group(1)}-{m.group(2)}" if m else None

    def match(self, doc) -> float:
        paren = 0
        black = 0
        for i in range(doc.page_count):
            page = doc[i]
            if not is_a4(page):
                continue
            if self.parse_grid(page.get_text("text")):
                paren += 1
            for path in page.get_drawings():
                if self.accept_line(path.get("color"), path.get("width") or 0.0):
                    black += 1
                    break
        score = 0.0
        if paren >= 2:
            score += 0.5
        if black >= 2:
            score += 0.4
        return min(score, 1.0)
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m pytest test_profiles.py -v`
Expected: PASS (6 test)

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/profiles/ruslan.py \
        Modules/Atelier/python/pdf_dxf_service/test_profiles.py
git commit -m "feat(atelier): Rusça kalıp mantığını RuslanProfile'a taşı"
```

---

### Task 3: Nipnaps (LATZEE) profili (`profiles/nipnaps.py`)

**Files:**
- Create: `Modules/Atelier/python/pdf_dxf_service/profiles/nipnaps.py`
- Test: `test_profiles.py` (ekleme; gerçek LATZEE PDF'iyle)

**Interfaces:**
- Consumes: `profiles.base`
- Produces: `class NipnapsProfile(ExtractionProfile)` — `name="nipnaps"`, `assembly="contact_sheet"`. `PALETTE: list[tuple[rgb,role]]` (6 renk: ortak/yesil/cyan/sari/kirmizi/mavi). `accept_line`: palet rengi + `width<=1.5`. `color_role`: palet eşlemesi. `is_tile_page`: A4 + kenar etiketi + >20 kabul edilen çizgi. `tile_labels(page)`: köşe birleştirme numaraları. `parse_product`/`parse_parts` (Almanca, Zuschnitt kesim listesi) / `parse_size` / `parse_measurements(doc)` (best-effort, word-bantlı). `match(doc)`: palet-dışı (non-black) renk veya Almanca anahtar kelime varsa yüksek.

- [ ] **Step 1: Write the failing test**

`test_profiles.py` dosyasına ekle:
```python
import os
from profiles.nipnaps import NipnapsProfile

LATZEE = os.path.join(os.path.dirname(__file__), "..", "..", "..", "..",
                      "docs", "superpowers", "plans",
                      "salopeta-Ebook-LATZEE_compressed-2.pdf")


def test_nipnaps_matches_real_latzee():
    doc = fitz.open(LATZEE)
    p = NipnapsProfile()
    assert p.match(doc) >= 0.5
    # Rusça profili bu dosyaya düşük skor vermeli (palet renkli + Almanca)
    assert RuslanProfile().match(doc) < 0.3


def test_nipnaps_color_role_palette():
    p = NipnapsProfile()
    assert p.color_role((0.0, 0.0, 0.0)) == "ortak"
    assert p.color_role((0.0, 0.5, 0.0)) == "yesil"
    assert p.color_role((0.0, 1.0, 1.0)) == "cyan"
    assert p.color_role((0.5, 0.5, 0.5)) is None  # palet dışı


def test_nipnaps_accept_thick_colored_line():
    p = NipnapsProfile()
    assert p.accept_line((0.0, 0.5, 0.0), 0.72) is True   # 0.72 (RU'da elenirdi)
    assert p.accept_line((0.0, 0.5, 0.0), 1.2) is True
    assert p.accept_line((0.5, 0.5, 0.5), 0.72) is False  # palet dışı renk


def test_nipnaps_detects_tile_pages_from_31():
    doc = fitz.open(LATZEE)
    p = NipnapsProfile()
    tile_pages = [i for i in range(doc.page_count) if p.is_tile_page(doc[i])]
    assert min(tile_pages) >= 30          # 0-index → sayfa 31
    assert len(tile_pages) >= 8


def test_nipnaps_parts_from_zuschnitt():
    doc = fitz.open(LATZEE)
    text = " ".join(doc[i].get_text("text") for i in range(doc.page_count))
    parts = NipnapsProfile().parse_parts(text)
    names = {pp["part_name"] for pp in parts}
    assert "Arka" in names and "Ön" in names and "Askı" in names
    arka = next(pp for pp in parts if pp["part_name"] == "Arka")
    assert arka["quantity"] == 1


def test_nipnaps_measurements_best_effort():
    doc = fitz.open(LATZEE)
    m = NipnapsProfile().parse_measurements(doc)
    # Best-effort: ya yapılandırılmış matris ya None; None değilse etiketler dolu.
    assert m is None or set(m["labels"]) >= {"OW", "TW", "HW"}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m pytest test_profiles.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'profiles.nipnaps'`

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/python/pdf_dxf_service/profiles/nipnaps.py`:
```python
"""Nipnaps (LATZ*EE) Almanca vektör kalıbı.

Gerçek dosyayla kalibre (salopeta-Ebook-LATZEE): kalıp karoları s.31+, A4.
Çizgiler ~0.72pt, BEDENLER RENK-KODLU (siyah=ortak + yeşil/cyan/sarı/kırmızı/mavi).
Karo köşelerinde "(satır,sütun)" YOK; kenar-eşleştirme numaraları var → assembly
contact_sheet (otomatik montaj ertelendi). Parça listesi s.6 "Zuschnitt" kesim
listesinden ("1 x Rückteil"...) güvenle okunur. Maßtabelle konumsal → best-effort.
"""

from __future__ import annotations

import re

from .base import ExtractionProfile, color_matches, is_a4, scan_lexicon

# Ölçülen RGB (0..1) → beden rolü. Siyah = bedenler-arası ortak çizgi/işaret.
PALETTE = [
    ((0.000, 0.000, 0.000), "ortak"),
    ((0.000, 0.502, 0.000), "yesil"),
    ((0.000, 1.000, 1.000), "cyan"),
    ((0.784, 0.784, 0.000), "sari"),
    ((0.620, 0.000, 0.000), "kirmizi"),
    ((0.125, 0.125, 1.000), "mavi"),
]
COLOR_TOL = 0.08
MAX_WIDTH_PT = 1.5

GERMAN_KEYS = ("latzhose", "latz*ee", "latzee", "nipnaps", "zuschnitt", "rückteil")

PRODUCT_LEXICON = {
    "latzhose": "tulum", "latz*ee": "tulum", "latzee": "tulum",
    "rock": "etek", "shorty": "şort",
}
SIZE_RE = re.compile(r"(\d{2,3})\s*/\s*(\d{2,3})")

# "1 x Rückteil" / "2 x Träger" gibi kesim listesi kalemleri.
CUT_RE = re.compile(r"(\d+)\s*x\s*([A-Za-zÄÖÜäöüß]+)")
PART_LEXICON = {
    "rückteil": "Arka", "ruckteil": "Arka",
    "vorderteil": "Ön",
    "träger": "Askı", "trager": "Askı",
    "tasche": "Cep", "taschen": "Cep",
    "latzbeleg": "Önlük Pervazı",
    "beleg": "Pervaz",
    "bund": "Bel",
}
MEASURE_LABELS = ("OW", "TW", "HW", "SL", "KH", "SW")


class NipnapsProfile(ExtractionProfile):
    name = "nipnaps"
    assembly = "contact_sheet"

    def color_role(self, color):
        for rgb, role in PALETTE:
            if color_matches(color, rgb, COLOR_TOL):
                return role
        return None

    def accept_line(self, color, width) -> bool:
        if self.color_role(color) is None:
            return False
        w = width or 0.0
        return not (w and w > MAX_WIDTH_PT)

    def _accepted_count(self, page, cap: int = 25) -> int:
        n = 0
        for path in page.get_drawings():
            if self.accept_line(path.get("color"), path.get("width") or 0.0):
                n += 1
                if n >= cap:
                    break
        return n

    def is_tile_page(self, page) -> bool:
        if not is_a4(page):
            return False
        if self._accepted_count(page) <= 20:
            return False
        return len(self.tile_labels(page)) > 0

    def tile_labels(self, page) -> list:
        """Köşe (üst/alt, sol/sağ kenar bölgesi) tek-haneli birleştirme numaraları."""
        w, h = page.rect.width, page.rect.height
        out = []
        for x0, y0, x1, y1, word, *_ in page.get_text("words"):
            t = word.strip()
            if not t.isdigit():
                continue
            near_x = x0 < 70 or x0 > w - 70
            near_y = y0 < 70 or y0 > h - 70
            if near_x and near_y:
                out.append(t)
        return out

    def parse_product(self, text: str):
        return scan_lexicon(text.lower(), PRODUCT_LEXICON)

    def parse_size(self, text: str):
        ms = SIZE_RE.findall(text)
        if not ms:
            return None
        first, last = ms[0], ms[-1]
        return f"{first[0]}/{first[1]}-{last[0]}/{last[1]}"

    def parse_parts(self, text: str) -> list:
        low = text.lower()
        found: dict = {}
        # 1) "N x Teil" kesim listesi (adet bilgisiyle).
        for qty, raw in CUT_RE.findall(low):
            name = PART_LEXICON.get(raw)
            if name:
                found[name] = max(found.get(name, 0), int(qty))
        # 2) Adetsiz geçen parça adları (en az 1).
        for key, name in PART_LEXICON.items():
            if key in low and name not in found:
                found[name] = 1
        return [{"part_name": n, "quantity": q} for n, q in found.items()]

    def parse_measurements(self, doc):
        """Best-effort: Maßtabelle satırlarını word y-bandıyla kur. Belirsizse None."""
        for i in range(min(doc.page_count, 8)):
            page = doc[i]
            words = page.get_text("words")  # (x0,y0,x1,y1,word,...)
            label_y = {}
            for x0, y0, x1, y1, w, *_ in words:
                if w in MEASURE_LABELS and x0 < 80 and w not in label_y:
                    label_y[w] = y0
            if len({"OW", "TW", "HW"} & set(label_y)) < 3:
                continue
            matrix = {}
            for label, y in label_y.items():
                nums = []
                for x0, y0, x1, y1, w, *_ in sorted(words, key=lambda r: r[0]):
                    if abs(y0 - y) < 4 and re.fullmatch(r"\d+[.,]?\d*", w):
                        nums.append(float(w.replace(",", ".")))
                if len(nums) >= 5:           # legend satırı sayı içermez → elenir
                    matrix[label] = nums
            if len({"OW", "TW", "HW"} & set(matrix)) >= 3:
                return {
                    "labels": list(matrix.keys()),
                    "matrix": matrix,
                    "unit": "cm",
                    "note": "Modellmasse ohne Nahtzugabe; best-effort, operatör doğrular",
                }
        return None

    def match(self, doc) -> float:
        non_black = 0
        text_hit = False
        for i in range(doc.page_count):
            page = doc[i]
            if not text_hit:
                low = page.get_text("text").lower()
                if any(k in low for k in GERMAN_KEYS):
                    text_hit = True
            if is_a4(page):
                for path in page.get_drawings():
                    role = self.color_role(path.get("color"))
                    if role and role != "ortak":
                        non_black += 1
                        break
        score = 0.0
        if non_black >= 2:
            score += 0.55
        if text_hit:
            score += 0.4
        return min(score, 1.0)
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m pytest test_profiles.py -v`
Expected: PASS (tüm profil testleri). Not: `test_nipnaps_measurements_best_effort` gerçek dosyada matris kurulamazsa `None` kabul eder (assertion `m is None or ...`).

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/profiles/nipnaps.py \
        Modules/Atelier/python/pdf_dxf_service/test_profiles.py
git commit -m "feat(atelier): NipnapsProfile — renk-kodlu beden + Almanca çıkarım"
```

---

### Task 4: Profil registry + tespit (`profiles/__init__.py`)

**Files:**
- Modify: `Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py`
- Test: `test_profiles.py` (ekleme)

**Interfaces:**
- Consumes: `RuslanProfile`, `NipnapsProfile`
- Produces:
  - `REGISTRY: list[ExtractionProfile]`
  - `detect_profile(doc) -> tuple[ExtractionProfile | None, float, list[dict]]` — en yüksek skorlu profil (skor ≥ 0.35), skor, ve `candidates` (skoru en yükseke 0.15 içinde olan profiller: `[{"name","score"}]`). Hiçbiri eşiği geçmezse `(None, best_score, candidates)`.

- [ ] **Step 1: Write the failing test**

`test_profiles.py` dosyasına ekle:
```python
from profiles import detect_profile, REGISTRY


def test_registry_has_both_profiles():
    names = {p.name for p in REGISTRY}
    assert {"ruslan", "nipnaps"} <= names


def test_detect_picks_nipnaps_for_latzee():
    doc = fitz.open(LATZEE)
    profile, score, candidates = detect_profile(doc)
    assert profile is not None and profile.name == "nipnaps"
    assert score >= 0.5


def test_detect_picks_ruslan_for_synthetic():
    profile, score, candidates = detect_profile(_ruslan_doc())
    assert profile is not None and profile.name == "ruslan"


def test_detect_returns_none_for_blank():
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # boş, çizimsiz
    profile, score, candidates = detect_profile(doc)
    assert profile is None
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m pytest test_profiles.py -v`
Expected: FAIL — `ImportError: cannot import name 'detect_profile'`

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py` (tam içerik):
```python
"""Profil registry ve otomatik tespit.

detect_profile tüm profillerin match() skorunu hesaplar, en yükseği seçer.
Skor eşiğin altındaysa None (operatöre kırmızı/elle). Skorlar yakınsa
candidates doldurulur (operatör seçsin → sarı).
"""

from __future__ import annotations

from .base import ExtractionProfile
from .nipnaps import NipnapsProfile
from .ruslan import RuslanProfile

REGISTRY: list = [RuslanProfile(), NipnapsProfile()]

MIN_SCORE = 0.35
TIE_GAP = 0.15


def detect_profile(doc):
    scored = [(p, p.match(doc)) for p in REGISTRY]
    scored.sort(key=lambda t: t[1], reverse=True)
    best, best_score = scored[0]
    candidates = [
        {"name": p.name, "score": round(s, 3)}
        for p, s in scored if best_score - s <= TIE_GAP and s >= MIN_SCORE
    ]
    if best_score < MIN_SCORE:
        return None, round(best_score, 3), candidates
    return best, round(best_score, 3), candidates
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m pytest test_profiles.py -v`
Expected: PASS (tüm testler)

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/profiles/__init__.py \
        Modules/Atelier/python/pdf_dxf_service/test_profiles.py
git commit -m "feat(atelier): profil registry + otomatik tespit (detect_profile)"
```

---

### Task 5: Motoru profil-güdümlü yap + renk→katman DXF (`converter.py`)

`converter.py` profile bağımsız motora dönüşür; renk-rolü→DXF katmanı yazar; iki montaj stratejisini (grid / contact_sheet) destekler. Mevcut sentetik testler korunur (ruslan profili üzerinden).

**Files:**
- Modify: `Modules/Atelier/python/pdf_dxf_service/converter.py` (tam yeniden yazım)
- Modify: `Modules/Atelier/python/pdf_dxf_service/test_converter.py` (yeni metadata anahtarlarına uyum)

**Interfaces:**
- Consumes: `profiles.detect_profile`, `ExtractionProfile`, `profiles.base` (PT_TO_MM, A4_*)
- Produces (DEĞİŞMEYEN dış arayüz — main.py bunları çağırır):
  - `convert_pdf(data: bytes, filename: str="") -> ConvertOutput`
  - `probe_pdf(data: bytes, filename: str="") -> dict`
  - re-export: `A4_W_MM`, `A4_H_MM`
  - `ConvertOutput` alanları aynı: `classification, confidence, dxf, metadata, errors`
  - Yeni metadata anahtarları: `profile`, `assembly`, `size_layers: list[str]`, `tiles: int`, `tile_labels: list[list[str]]`, `measurements`, `fabric_usage` (şimdilik `None`/`{}`), `parts`, `product_type`, `size_range`, `scale_verified`, `scale_deviation_mm`, `segment_count`, ve grid montajında `grid: {"rows","cols"}`.

- [ ] **Step 1: Write the failing test**

`test_converter.py`'yi güncelle — mevcut grid testleri korunur, yeni anahtar/LATZEE testi eklenir. Şu testleri **değiştir/ekle** (diğer mevcut testler aynı kalır):
```python
import os
from converter import convert_pdf, probe_pdf, A4_W_MM, A4_H_MM

LATZEE = os.path.join(os.path.dirname(__file__), "..", "..", "..", "..",
                      "docs", "superpowers", "plans",
                      "salopeta-Ebook-LATZEE_compressed-2.pdf")


def test_grid_profile_sets_metadata():
    out = convert_pdf(_build_pdf(), "kombinezon_116-134.pdf")
    assert out.metadata["profile"] == "ruslan"
    assert out.metadata["assembly"] == "grid"
    assert out.metadata["grid"] == {"rows": 2, "cols": 2}
    assert out.metadata["segment_count"] == 60
    assert "BEDEN_" not in out.dxf or "KALIP" in out.dxf  # ruslan tek katman


def test_latzee_converts_with_color_layers():
    data = open(LATZEE, "rb").read()
    out = convert_pdf(data, "latzee.pdf")
    assert out.metadata["profile"] == "nipnaps"
    assert out.metadata["assembly"] == "contact_sheet"
    assert out.metadata["segment_count"] > 200
    assert out.dxf and "ENTITIES" in out.dxf
    # En az iki beden rengi ayrı katmana yazıldı.
    assert len(out.metadata["size_layers"]) >= 2
    assert any(L.startswith("BEDEN_") for L in out.metadata["size_layers"])
    assert out.classification in ("green", "yellow")


def test_latzee_probe_is_vector_tiled():
    data = open(LATZEE, "rb").read()
    p = probe_pdf(data, "latzee.pdf")
    assert p["kind"] == "vector_tiled"
    assert p["profile"] == "nipnaps"
```
(Mevcut `test_skips_cover_and_stitches_grid`, `test_extracts_product_and_size`, `test_classification_green_when_complete`, `test_exact_a4_has_zero_deviation`, `test_non_uniform_tiles_flagged`, `test_probe_detects_vector_tiled`, `test_probe_detects_raster`, `test_probe_invalid_for_non_pdf`, `test_non_pdf_is_red` — **değişmez**, geçmeye devam etmeli.)

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m pytest test_converter.py -v`
Expected: FAIL — yeni testler `KeyError: 'profile'` / eski testler de motor henüz profil kullanmadığı için kırılabilir.

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/python/pdf_dxf_service/converter.py` (tam yeni içerik):
```python
"""PDF → DXF kalıp dönüştürücü — profil-güdümlü motor (PyMuPDF + ezdxf).

Motor satıcıdan bağımsızdır: detect_profile() doğru profili seçer, profil
karo/çizgi/renk/ızgara/sözlük kurallarını verir. Montaj stratejisi profile
bağlıdır: "grid" (Rusça, (r,c) ile birleştirir) | "contact_sheet" (Nipnaps,
kenar-etiketli karoları yan yana dizer, otomatik montaj ertelendi).

Segmentler renk-ROLÜNE göre ayrı DXF katmanına yazılır (renk-kodlu bedenler).
Gerçek beden adı ataması operatöre bırakılır.
"""

from __future__ import annotations

import io
from dataclasses import dataclass, field

import ezdxf
import fitz  # PyMuPDF

from profiles import detect_profile
from profiles.base import A4_H_MM, A4_W_MM, PT_TO_MM  # noqa: F401 (re-export)

# Rol → (DXF katman adı, ACI rengi).
ROLE_LAYER = {
    "ortak":   ("KALIP", 7),
    "yesil":   ("BEDEN_YESIL", 3),
    "cyan":    ("BEDEN_CYAN", 4),
    "sari":    ("BEDEN_SARI", 2),
    "kirmizi": ("BEDEN_KIRMIZI", 1),
    "mavi":    ("BEDEN_MAVI", 5),
}
LABEL_LAYER = "MONTAJ_ETIKET"
CONTACT_COLS = 4          # kontak-sayfasında karo başına sütun
CONTACT_GAP_MM = 20.0     # karolar arası boşluk


@dataclass
class ConvertOutput:
    classification: str
    confidence: float
    dxf: str | None
    metadata: dict = field(default_factory=dict)
    errors: list = field(default_factory=list)


def _layer_for(role: str) -> tuple[str, int]:
    return ROLE_LAYER.get(role, ("KALIP", 7))


def _page_segments(page, profile):
    """(role, x1,y1,x2,y2) mm (sayfa-yerel), profile'ın kabul ettiği çizgiler."""
    out = []
    for path in page.get_drawings():
        color = path.get("color")
        width = path.get("width") or 0.0
        if not profile.accept_line(color, width):
            continue
        role = profile.color_role(color) or "ortak"
        for it in path.get("items", []):
            k = it[0]
            if k == "l":
                p1, p2 = it[1], it[2]
                out.append((role, p1.x, p1.y, p2.x, p2.y))
            elif k == "c":
                p1, p2 = it[1], it[4]
                out.append((role, p1.x, p1.y, p2.x, p2.y))
            elif k == "re":
                r = it[1]
                cs = [(r.x0, r.y0), (r.x1, r.y0), (r.x1, r.y1), (r.x0, r.y1)]
                for i in range(4):
                    a, b = cs[i], cs[(i + 1) % 4]
                    out.append((role, a[0], a[1], b[0], b[1]))
    return [(s[0],) + tuple(round(v * PT_TO_MM, 3) for v in s[1:]) for s in out]


def _build_dxf(lines, texts) -> str:
    doc = ezdxf.new("R2010")
    doc.units = ezdxf.units.MM
    msp = doc.modelspace()
    used_layers = {role for role, *_ in lines}
    for role in used_layers:
        name, aci = _layer_for(role)
        if name not in doc.layers:
            doc.layers.add(name, color=aci)
    if texts and LABEL_LAYER not in doc.layers:
        doc.layers.add(LABEL_LAYER, color=6)
    for role, x1, y1, x2, y2 in lines:
        name, _ = _layer_for(role)
        msp.add_line((x1, y1), (x2, y2), dxfattrs={"layer": name})
    for x, y, s in texts:
        msp.add_text(s, height=8, dxfattrs={"layer": LABEL_LAYER, "insert": (x, y)})
    buf = io.StringIO()
    doc.write(buf)
    return buf.getvalue()


def _stem(filename: str) -> str:
    base = (filename or "kalip").rsplit("/", 1)[-1].rsplit("\\", 1)[-1]
    return base.rsplit(".", 1)[0] or "kalip"


def _collect_tiles(doc, profile):
    """Profile'ın kalıp karosu saydığı sayfalar: (labels, w_mm, h_mm, segments)."""
    tiles = []
    for i in range(doc.page_count):
        page = doc[i]
        if not profile.is_tile_page(page):
            continue
        segs = _page_segments(page, profile)
        if not segs:
            continue
        w_mm = page.rect.width * PT_TO_MM
        h_mm = page.rect.height * PT_TO_MM
        labels = profile.tile_labels(page) if profile.assembly == "contact_sheet" \
            else profile.parse_grid(page.get_text("text"))
        tiles.append((labels, w_mm, h_mm, segs))
    return tiles


def _assemble_grid(tiles):
    """(r,c) etiketli karoları tek koordinata birleştir. → (lines, meta, errors)."""
    errors = []
    coords = [(lbl, w, h, segs) for lbl, w, h, segs in tiles if lbl]
    rows = max((lbl[0] for lbl, *_ in coords), default=0)
    cols = max((lbl[1] for lbl, *_ in coords), default=0)
    sizes = [(w, h) for _, w, h, _ in coords]
    w0, h0 = sizes[0]
    max_dev = max(max(abs(w - A4_W_MM), abs(h - A4_H_MM)) for w, h in sizes)
    uniform = all(abs(w - w0) <= 0.5 and abs(h - h0) <= 0.5 for w, h in sizes)
    scale_ok = max_dev <= 3.0 and uniform
    if max_dev > 3.0:
        errors.append(f"Karo boyutu A4'ten {max_dev:.1f}mm sapıyor; ölçek güvenilmez.")
    if not uniform:
        errors.append("Karo boyutları tekdüze değil; birleştirmede kayma olabilir.")
    lines = []
    for (r, c), w, h, segs in coords:
        ox = (c - 1) * w0
        oy = (rows - r) * h0
        for role, x1, y1, x2, y2 in segs:
            lines.append((role, round(ox + x1, 3), round(oy + (h0 - y1), 3),
                          round(ox + x2, 3), round(oy + (h0 - y2), 3)))
    meta = {"grid": {"rows": rows, "cols": cols}, "tiles": len(coords),
            "scale_verified": scale_ok, "scale_deviation_mm": round(max_dev, 2)}
    grid_full = rows * cols == len(coords)
    meta["_grid_full"] = grid_full
    return lines, meta, errors


def _assemble_contact_sheet(tiles):
    """Karoları sayfa sırasına göre ızgarada yan yana diz (montaj ETMEZ).

    Her karo kenar-eşleştirme etiketleriyle birlikte DXF'e konur; kalıpçı CAD'de
    etiketleri eşleyerek birleştirir. → (lines, texts, meta, errors)."""
    lines, texts = [], []
    tile_labels = []
    w0, h0 = tiles[0][1], tiles[0][2]
    max_dev = max(max(abs(w - A4_W_MM), abs(h - A4_H_MM)) for _, w, h, _ in tiles)
    scale_ok = max_dev <= 3.0
    errors = []
    if not scale_ok:
        errors.append(f"Karo boyutu A4'ten {max_dev:.1f}mm sapıyor; ölçek güvenilmez.")
    for idx, (labels, w, h, segs) in enumerate(tiles):
        gx = (idx % CONTACT_COLS) * (w0 + CONTACT_GAP_MM)
        gy = -(idx // CONTACT_COLS) * (h0 + CONTACT_GAP_MM)
        for role, x1, y1, x2, y2 in segs:
            # PDF y aşağı → DXF y yukarı çevir, sonra ızgara ofseti.
            lines.append((role, round(gx + x1, 3), round(gy + (h0 - y1), 3),
                          round(gx + x2, 3), round(gy + (h0 - y2), 3)))
        tile_labels.append(list(labels or []))
        if labels:
            texts.append((gx + 4, gy + h0 - 4, "#%d:%s" % (idx + 1, ",".join(labels))))
    meta = {"tiles": len(tiles), "tile_labels": tile_labels,
            "scale_verified": scale_ok, "scale_deviation_mm": round(max_dev, 2),
            "_grid_full": True}
    return lines, texts, meta, errors


def convert_pdf(data: bytes, filename: str = "") -> ConvertOutput:
    try:
        doc = fitz.open(stream=data, filetype="pdf")
    except Exception as exc:  # noqa: BLE001
        return ConvertOutput("red", 0.0, None, {}, [f"PDF açılamadı: {exc}"])
    if doc.page_count == 0:
        return ConvertOutput("red", 0.0, None, {}, ["PDF boş."])

    profile, score, candidates = detect_profile(doc)
    if profile is None:
        return ConvertOutput("red", round(score * 100, 1), None,
                             {"name": _stem(filename), "profile_candidates": candidates},
                             ["Tanınan satıcı profili yok (vektör değil ya da yeni şema)."])

    tiles = _collect_tiles(doc, profile)
    if not tiles:
        return ConvertOutput("red", 8.0, None,
                             {"name": _stem(filename), "profile": profile.name},
                             ["Kalıp karosu bulunamadı (kabul edilen çizgi yok)."])

    errors = []
    texts = []
    if profile.assembly == "grid":
        lines, ameta, aerr = _assemble_grid(tiles)
    else:
        lines, texts, ameta, aerr = _assemble_contact_sheet(tiles)
    errors += aerr

    full_text = " ".join(doc[i].get_text("text") for i in range(doc.page_count))
    product_type = profile.parse_product(full_text)
    size_range = profile.parse_size(full_text)
    parts = profile.parse_parts(full_text)
    measurements = profile.parse_measurements(doc)
    if not parts:
        errors.append("Parça etiketi okunamadı (operatör doldurmalı).")
    if measurements is None:
        errors.append("Ölçü tablosu güvenle okunamadı (operatör doğrulamalı).")

    size_layers = sorted({_layer_for(role)[0] for role, *_ in lines
                          if _layer_for(role)[0].startswith("BEDEN_")})
    dxf = _build_dxf(lines, texts)

    grid_full = ameta.pop("_grid_full", True)
    scale_ok = ameta.get("scale_verified", False)
    classification = "green" if (scale_ok and grid_full and len(lines) > 50
                                 and (profile.assembly == "grid" or len(size_layers) >= 2)) \
        else "yellow"
    conf = 35.0 + (25 if scale_ok else 0) + (20 if grid_full else 0) \
        + (10 if product_type else 0) + (10 if len(lines) > 200 else 0)

    metadata = {
        "name": _stem(filename),
        "profile": profile.name,
        "assembly": profile.assembly,
        "profile_candidates": candidates,
        "product_type": product_type,
        "size_range": size_range,
        "size_layers": size_layers,
        "segment_count": len(lines),
        "parts": parts,
        "measurements": measurements,
        "fabric_usage": None,
        **ameta,
    }
    return ConvertOutput(classification, round(min(conf, 99.0), 1), dxf, metadata, errors)


def probe_pdf(data: bytes, filename: str = "") -> dict:
    """Hızlı ön-kontrol: profil tutuyor mu, vektör/raster mı? DXF üretmez."""
    try:
        doc = fitz.open(stream=data, filetype="pdf")
    except Exception as exc:  # noqa: BLE001
        return {"kind": "invalid", "pages": 0, "note": f"PDF açılamadı: {exc}"}
    n = doc.page_count
    if n == 0:
        return {"kind": "empty", "pages": 0}

    profile, score, candidates = detect_profile(doc)
    img_pages = sum(1 for i in range(n) if doc[i].get_images())
    if profile is not None:
        tiles = _collect_tiles(doc, profile)
        if tiles:
            return {"kind": "vector_tiled", "pages": n, "profile": profile.name,
                    "score": score, "tiles": len(tiles)}
        return {"kind": "vector", "pages": n, "profile": profile.name, "score": score}
    if img_pages > 0:
        return {"kind": "raster", "pages": n, "image_pages": img_pages,
                "profile_candidates": candidates}
    return {"kind": "empty", "pages": n}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m pytest test_converter.py test_profiles.py -v`
Expected: PASS — hem yeni LATZEE/profil testleri hem mevcut Rusça sentetik regresyon testleri.

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/converter.py \
        Modules/Atelier/python/pdf_dxf_service/test_converter.py
git commit -m "feat(atelier): profil-güdümlü motor + renk→katman DXF + contact_sheet montaj"
```

---

### Task 6: `patterns` tablosuna çıkarım kolonları + model

**Files:**
- Create: `Modules/Atelier/database/migrations/2026_06_24_150000_add_extraction_data_to_patterns_table.php`
- Modify: `Modules/Atelier/Models/Pattern.php:26-36` (fillable + casts)
- Test: `Modules/Atelier/tests/Feature/PatternExtractionColumnsTest.php` (Create)

**Interfaces:**
- Produces: `patterns` tablosunda `size_layers (json)`, `color_size_map (json)`, `measurements (json)`, `fabric_usage (json)` kolonları; `Pattern` modelinde bunlar fillable + `array` cast.

- [ ] **Step 1: Write the failing test**

`Modules/Atelier/tests/Feature/PatternExtractionColumnsTest.php`:
```php
<?php

use Modules\Atelier\Models\Pattern;

it('persists extraction json columns as arrays', function () {
    $pattern = Pattern::create([
        'name'           => 'Latzee',
        'product_type'   => 'tulum',
        'size_layers'    => ['BEDEN_YESIL', 'BEDEN_CYAN'],
        'color_size_map' => ['#00FF00' => 'yesil'],
        'measurements'   => ['labels' => ['OW', 'TW'], 'matrix' => ['OW' => [22.5]]],
        'fabric_usage'   => ['50/56' => '30cm'],
    ]);

    $fresh = $pattern->fresh();
    expect($fresh->size_layers)->toBe(['BEDEN_YESIL', 'BEDEN_CYAN']);
    expect($fresh->measurements['labels'])->toBe(['OW', 'TW']);
    expect($fresh->fabric_usage)->toBe(['50/56' => '30cm']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PatternExtractionColumnsTest`
Expected: FAIL — `SQLSTATE ... column "size_layers" does not exist`

- [ ] **Step 3: Write minimal implementation**

`Modules/Atelier/database/migrations/2026_06_24_150000_add_extraction_data_to_patterns_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            // Renk-kodlu beden DXF katmanları (BEDEN_YESIL...). Filtre değil → JSON.
            $table->json('size_layers')->nullable()->after('size_range');
            // Renk(hex)→beden rolü kanıtı; gerçek beden adı operatörce atanır.
            $table->json('color_size_map')->nullable()->after('size_layers');
            // Maßtabelle best-effort matrisi {labels, matrix, unit, note}.
            $table->json('measurements')->nullable()->after('color_size_map');
            // Beden→kumaş tüketimi {"50/56":"30cm @150"}.
            $table->json('fabric_usage')->nullable()->after('measurements');
        });
    }

    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropColumn(['size_layers', 'color_size_map', 'measurements', 'fabric_usage']);
        });
    }
};
```

`Modules/Atelier/Models/Pattern.php` — `$fillable` dizisine ekle (satır 30 `'notes', 'created_by',` öncesi):
```php
        'size_layers', 'color_size_map', 'measurements', 'fabric_usage',
```
ve `$casts` dizisine ekle (satır 35 `'scale_deviation_mm' => 'decimal:2',` sonrası):
```php
        'size_layers'    => 'array',
        'color_size_map' => 'array',
        'measurements'   => 'array',
        'fabric_usage'   => 'array',
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=PatternExtractionColumnsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/database/migrations/2026_06_24_150000_add_extraction_data_to_patterns_table.php \
        Modules/Atelier/Models/Pattern.php \
        Modules/Atelier/tests/Feature/PatternExtractionColumnsTest.php
git commit -m "feat(atelier): patterns'a çıkarım JSON kolonları (size_layers/measurements/...)"
```

---

### Task 7: `approve()` yeni metadata'yı map'ler

**Files:**
- Modify: `Modules/Atelier/Services/Conversion/ConversionPipelineService.php:85-123` (`approve()`)
- Test: `Modules/Atelier/tests/Feature/ConversionApprovalTest.php` (Create)

**Interfaces:**
- Consumes: `ConversionJob.error_report['metadata']` (Task 5 metadata: `profile`, `size_layers`, `measurements`, `fabric_usage`, `parts`, vb.)
- Produces: `approve()` ürettiği `Pattern` kaydında `vendor=metadata['profile']`, `size_layers`, `measurements`, `fabric_usage`, `color_size_map` dolu; `parts` ilişkisi adet bilgisiyle yazılı.

- [ ] **Step 1: Write the failing test**

`Modules/Atelier/tests/Feature/ConversionApprovalTest.php`:
```php
<?php

use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Services\Conversion\ConversionPipelineService;

it('maps extraction metadata onto the approved pattern', function () {
    $job = ConversionJob::create([
        'source_pdf_path' => 'atelier/conversions/pdf/latzee.pdf',
        'output_dxf_path' => 'atelier/conversions/dxf/latzee.dxf',
        'classification'  => ConversionJob::CLASS_YELLOW,
        'status'          => ConversionJob::STATUS_NEEDS_REVIEW,
        'error_report'    => ['metadata' => [
            'name'         => 'Latzee',
            'profile'      => 'nipnaps',
            'product_type' => 'tulum',
            'size_range'   => '50/56-170/176',
            'size_layers'  => ['BEDEN_YESIL', 'BEDEN_CYAN'],
            'measurements' => ['labels' => ['OW'], 'matrix' => ['OW' => [22.5]]],
            'fabric_usage' => null,
            'parts'        => [
                ['part_name' => 'Arka', 'quantity' => 1],
                ['part_name' => 'Askı', 'quantity' => 2],
            ],
        ]],
    ]);

    $pattern = app(ConversionPipelineService::class)->approve($job);

    expect($pattern->vendor)->toBe('nipnaps');
    expect($pattern->size_layers)->toBe(['BEDEN_YESIL', 'BEDEN_CYAN']);
    expect($pattern->measurements['matrix']['OW'])->toBe([22.5]);
    expect($pattern->parts)->toHaveCount(2);
    expect($pattern->parts->firstWhere('part_name', 'Askı')->quantity)->toBe(2);
    expect($job->fresh()->status)->toBe(ConversionJob::STATUS_APPROVED);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConversionApprovalTest`
Expected: FAIL — `expect($pattern->vendor)->toBe('nipnaps')` başarısız (şu an `vendor` set edilmiyor).

- [ ] **Step 3: Write minimal implementation**

`ConversionPipelineService.php` — `approve()` içindeki `Pattern::create([...])` çağrısını genişlet (satır 90-100). Mevcut alanlar korunur; şunlar eklenir:
```php
            $pattern = Pattern::create([
                'name'               => $meta['name'] ?? pathinfo((string) $job->source_pdf_path, PATHINFO_FILENAME),
                'product_type'       => $meta['product_type'] ?? 'belirsiz',
                'size_range'         => $meta['size_range'] ?? null,
                'vendor'             => $meta['profile'] ?? null,
                'size_layers'        => $meta['size_layers'] ?? null,
                'color_size_map'     => $meta['color_size_map'] ?? null,
                'measurements'       => $meta['measurements'] ?? null,
                'fabric_usage'       => $meta['fabric_usage'] ?? null,
                'scale_verified'     => (bool) ($meta['scale_verified'] ?? false),
                'scale_deviation_mm' => $meta['scale_deviation_mm'] ?? null,
                'dxf_path'           => $job->output_dxf_path,
                'pdf_path'           => $job->source_pdf_path,
                'status'             => Pattern::STATUS_APPROVED,
                'created_by'         => $reviewerId,
            ]);
```
(`parts` döngüsü zaten adet okuyor — `max(1, (int) ($part['quantity'] ?? 1))` — değişmez.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ConversionApprovalTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/Conversion/ConversionPipelineService.php \
        Modules/Atelier/tests/Feature/ConversionApprovalTest.php
git commit -m "feat(atelier): approve() çıkarım metadata'sını Pattern'a map'ler (vendor/ölçü/beden)"
```

---

### Task 8: Onay ekranında çıkarım gösterimi (`Conversions.vue`)

Operatör onay ekranında profil, bulunan beden katmanları, parçalar ve ölçü tablosu durumunu görür (otomatik imza + onay kararının operatör tarafı).

**Files:**
- Modify: `Modules/Atelier/Resources/assets/js/Pages/Conversions.vue`

**Interfaces:**
- Consumes: her `job.error_report.metadata` (`profile`, `assembly`, `size_layers`, `parts`, `measurements`, `profile_candidates`) ve `job.error_report.errors`.

- [ ] **Step 1: Önce mevcut yapıyı oku**

Run: `sed -n '1,200p' Modules/Atelier/Resources/assets/js/Pages/Conversions.vue`
Amaç: bir işin satırının/kartının nerede render edildiğini ve `metadata`/`error_report`'a nasıl erişildiğini görmek (prop adı, döngü değişkeni `job`).

- [ ] **Step 2: Çıkarım özeti bileşenini ekle**

İş kartı/satırı içine (metadata erişiminin olduğu yere) şu özet bloğunu ekle. `job.error_report?.metadata` yapısına göre değişken adını eşle:
```vue
<div v-if="job.error_report?.metadata" class="mt-2 text-sm space-y-1">
  <div>
    <span class="font-medium">Profil:</span>
    {{ job.error_report.metadata.profile ?? '—' }}
    <span class="text-gray-500">({{ job.error_report.metadata.assembly }})</span>
  </div>
  <div v-if="job.error_report.metadata.size_layers?.length">
    <span class="font-medium">Beden katmanları:</span>
    {{ job.error_report.metadata.size_layers.join(', ') }}
  </div>
  <div v-if="job.error_report.metadata.parts?.length">
    <span class="font-medium">Parçalar:</span>
    <span v-for="p in job.error_report.metadata.parts" :key="p.part_name" class="mr-2">
      {{ p.quantity }}× {{ p.part_name }}
    </span>
  </div>
  <div>
    <span class="font-medium">Ölçü tablosu:</span>
    <span :class="job.error_report.metadata.measurements ? 'text-green-600' : 'text-amber-600'">
      {{ job.error_report.metadata.measurements ? 'okundu' : 'operatör doğrulamalı' }}
    </span>
  </div>
  <ul v-if="job.error_report.errors?.length" class="list-disc list-inside text-amber-600">
    <li v-for="(e, i) in job.error_report.errors" :key="i">{{ e }}</li>
  </ul>
</div>
```

- [ ] **Step 3: Derleme kontrolü**

Run: `npm run build` (veya proje neyse: `vite build`)
Expected: Hatasız derlenir; Conversions.vue yeni bloğu içerir.

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/Conversions.vue
git commit -m "feat(atelier): onay ekranında profil/beden/parça/ölçü çıkarım özeti"
```

---

## Self-Review Notları (plan yazarı)

- **Spec §3 profil mimarisi** → Task 1–4. **§4 Nipnaps + renk→katman** → Task 3, 5. **§5 veri modeli** → Task 6, 7. **§6 Laravel akışı/triyaj** → Task 5 (classification) + 7 + 8. **§7 test** → her task TDD + Task 5 LATZEE golden.
- **Spec'ten sapma (onaylı):** §4.1'deki "(satır,sütun)" varsayımı yanlıştı; Nipnaps kenar-etiketli → `assembly="contact_sheet"`, otomatik montaj ertelendi (kullanıcı onayı alındı). Maßtabelle güvenilir matris yerine **best-effort + operatör doğrulaması** (gerçek dosyada konumsal/karışık olduğu kanıtlandı).
- **Renk→gerçek beden adı** kasıtlı olarak atanmaz (DXF katmanı renk-rolüyle; ad operatörde) — uydurma önlenir.
- **Tip tutarlılığı:** `detect_profile` → `(profile, score, candidates)` her yerde aynı; `ConvertOutput` alanları main.py sözleşmesiyle uyumlu; `metadata['profile'/'size_layers'/'measurements'/'parts']` üretildiği (Task 5) ve tüketildiği (Task 7, 8) yerde aynı adlar.
```
```

---

## Yürütme

Plan tamamlandı ve `docs/superpowers/plans/2026-06-24-satici-profilli-kalip-cikarimi.md` dosyasına kaydedildi.
