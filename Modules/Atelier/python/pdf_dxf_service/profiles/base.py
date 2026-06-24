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
