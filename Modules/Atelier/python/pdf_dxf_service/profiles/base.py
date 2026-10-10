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


def effective_color(path) -> tuple | None:
    """get_drawings() path'inin "mürekkep" rengi: STROKE (color) varsa o, yoksa
    FILL. Bazı üreticiler çizgiyi gerçek bir stroke olarak DEĞİL, ince kapalı
    bir dolgu poligonu (type='f', color=None, fill=(r,g,b)) olarak basar — bkz.
    gerçek vaka: vikroim.ru "shorty/svobodnye-letnie-shorty-summer" PDF'i, tüm
    kesim çizgileri color=None/width=None, yalnızca fill taşıyor. accept_line()/
    color_role() rengi HER ZAMAN bu fonksiyondan almalı, path.get("color") ile
    DOĞRUDAN DEĞİL — aksi halde böyle bir PDF'te hiçbir çizgi kabul edilmez."""
    return path.get("color") or path.get("fill")


def is_a4(page) -> bool:
    w, h = page.rect.width * PT_TO_MM, page.rect.height * PT_TO_MM
    return abs(w - A4_W_MM) <= A4_TOL_MM and abs(h - A4_H_MM) <= A4_TOL_MM


def hue_role(rgb) -> str:
    """Vektör path rengi (0..1 RGB) → rol adı, saf HUE'den (satıcı sözlüğü YOK).

    converter.vectorize_vector_generic ve GenericGridProfile.color_role tarafından
    paylaşılır — profilsiz/marka-bağımsız yollarda "hangi satıcı" bilmeden
    beden-katman ayrımı yapabilmek için."""
    if not rgb:
        return "ortak"
    try:
        r, g, b = float(rgb[0]), float(rgb[1]), float(rgb[2])
    except (TypeError, ValueError, IndexError):
        return "ortak"
    mx, mn = max(r, g, b), min(r, g, b)
    c = mx - mn
    if c <= 0.18:
        return "ortak"
    if mx == r:
        h = ((g - b) / c) % 6
    elif mx == g:
        h = ((b - r) / c) + 2
    else:
        h = ((r - g) / c) + 4
    h = (h * 60.0) % 360.0
    refs = [(0.0, "kirmizi"), (60.0, "sari"), (120.0, "yesil"),
            (180.0, "cyan"), (240.0, "mavi"), (300.0, "mor")]
    return min(refs, key=lambda hr: min(abs(h - hr[0]), 360.0 - abs(h - hr[0])))[1]


class ExtractionProfile:
    """Tüm profillerin tabanı. Alt sınıflar gerekli metotları ezer."""

    name: str = "base"
    assembly: str = "grid"  # "grid" | "contact_sheet"

    # Izgara karolarının beklenen fiziksel boyutu (mm) — "grid" birleştirmede
    # sapma/ölçek kontrolü için (bkz. converter._assemble_grid). Çoğu satıcı
    # (Ruslan, Nipnaps) karoyu tam A4'e basar; bu varsayılan onlar için doğru.
    # Karo boyutu KASITLI olarak A4 DEĞİLSE (ör. Burda'nın kırpılmış ~170×257mm
    # karoları) alt sınıf None atar — motor o zaman yalnızca TEKDÜZELİĞİ
    # (tüm karolar birbirine eşit mi) kontrol eder, A4'ten sapmayı değil.
    expected_tile_mm: tuple | None = (A4_W_MM, A4_H_MM)

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

    def parse_production(self, doc) -> dict:
        """Kalıp Kartı üretim bilgisi (en-iyi-çaba): sizes, size_range, variants,
        parts[{part_no,part_name,quantity,variants}], fabric_usage, seam_allowance_cm,
        body_measurements[{basis,group,point_key,values}]. Okunamayan anahtar HİÇ
        döndürülmez — PHP tarafı mevcut/elle girilmiş değeri korur."""
        return {}

    def own_tile_label(self, page) -> str | None:
        """Karonun KENDİ sayfasında taşıdığı ham etiket (ör. Burda'nın dev
        soluk '01' numarası) — henüz (row,col) değil, build_grid_map()'teki
        haritada aranacak anahtar. Etiket karo sayfasının kendi metninden
        (parse_grid gibi) doğrudan (row,col) çıkarılabiliyorsa (ör. Ruslan)
        bu metoda gerek YOK, None döner."""
        return None

    def build_grid_map(self, doc) -> dict | None:
        """Karo etiketleri karoların KENDİ sayfasında değil, ayrı bir 'genel
        bakış/montaj' sayfasında basılıysa (ör. Burda — bkz. BurdaProfile)
        doc genelinde BİR KEZ çağrılır; {own_tile_label(): (row,col)} döner.
        None ise motor mevcut parse_grid(page_text) yoluna düşer (Ruslan)."""
        return None
