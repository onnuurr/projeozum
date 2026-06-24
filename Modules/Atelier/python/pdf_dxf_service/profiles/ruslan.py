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
