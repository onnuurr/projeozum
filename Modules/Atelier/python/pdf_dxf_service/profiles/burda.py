"""Burda (Nordic/Alman/Uluslararası) çok-sayfalı vektör kalıbı.

Ruslan'dan farkı: karo kendi sayfasında "(satır,sütun)" TAŞIMAZ — yalnızca dev
(≈150pt+), soluk gri, iki haneli bir seri numarası ("01".."NN") taşır. Gerçek
(satır,sütun) konumu AYRI bir "genel bakış/montaj" sayfasında (aynı numaralar
küçük punto ile düzenli bir ızgarada basılı) kodlanır — bu yüzden
build_grid_map(doc)/own_tile_label(page) ayrımı gerekiyor (bkz. base.py).

Kalıp: neredeyse-siyah (~0.11,0.11,0.11) çizgiler, "Kontrolmål/Kontrollmall/
Kontrolmål 10x10cm" kontrol karesi, kenarlarda montaj üçgenleri (komşu karoyla
hizalama — okunmaz, kullanılmaz). "Burda" telif metni ayırt edici.
"""

from __future__ import annotations

import re

from .base import ExtractionProfile, color_matches, scan_lexicon

BLACK = (0.0, 0.0, 0.0)
BLACK_TOL = 0.2
MAX_WIDTH_PT = 1.4

TILE_LABEL_MIN_SIZE = 100.0   # karonun kendi dev numarası (~150pt gözlendi)
OVERVIEW_LABEL_SIZE_RANGE = (14.0, 40.0)  # genel bakış sayfasındaki küçük numaralar
OVERVIEW_MIN_LABELS = 6        # bir sayfayı "genel bakış" saymak için asgari etiket

TILE_NUM_RE = re.compile(r'^\d{1,2}$')

SIZE_RE = re.compile(r'(?:str|stl|size|größe|gr)\.?\s*:?\s*(\d{2,3})\s*[-–]\s*(\d{2,3})', re.IGNORECASE)

PRODUCT_LEXICON = {
    'solkjole': 'elbise', 'solklänning': 'elbise', 'kjole': 'elbise',
    'klänning': 'elbise', 'kleid': 'elbise', 'dress': 'elbise',
    'bukser': 'pantolon', 'byxor': 'pantolon', 'hose': 'pantolon',
    'trousers': 'pantolon', 'pants': 'pantolon',
    'jakke': 'ceket', 'jacka': 'ceket', 'jacke': 'ceket', 'jacket': 'ceket',
    'skjorte': 'gömlek', 'skjorta': 'gömlek', 'hemd': 'gömlek', 'shirt': 'gömlek',
    'topp': 'üst', 'top': 'üst',
    'nederdel': 'etek', 'kjol': 'etek', 'rock': 'etek', 'skirt': 'etek',
    'shorts': 'şort',
    'kåbe': 'palto', 'kappa': 'palto', 'mantel': 'palto', 'coat': 'palto',
}


class BurdaProfile(ExtractionProfile):
    name = 'burda'
    assembly = 'grid'
    # Burda karoları A4'e basılmaz (gözlenen: ~170×257mm, kırpılmış) — A4 sapma
    # kontrolü bu profilde ANLAMSIZ; motor yalnızca karo TEKDÜZELİĞİNİ kontrol eder.
    expected_tile_mm = None

    def accept_line(self, color, width) -> bool:
        if not color_matches(color, BLACK, BLACK_TOL):
            return False
        w = width or 0.0
        return not (w and w > MAX_WIDTH_PT)

    def color_role(self, color):
        return 'ortak'

    def parse_grid(self, text: str):
        # Burda karoları (satır,sütun) TAŞIMAZ — her zaman None; motor
        # own_tile_label()/build_grid_map() ikilisine düşer (bkz. converter.py).
        return None

    def own_tile_label(self, page) -> str | None:
        best = None
        for block in page.get_text('dict')['blocks']:
            for line in block.get('lines', []):
                for span in line['spans']:
                    t = span['text'].strip()
                    if TILE_NUM_RE.fullmatch(t) and span['size'] >= TILE_LABEL_MIN_SIZE:
                        if best is None or span['size'] > best[1]:
                            best = (t, span['size'])
        return best[0] if best else None

    def build_grid_map(self, doc) -> dict | None:
        best_page = None
        best_labels: list = []
        for i in range(doc.page_count):
            labels = self._overview_labels(doc[i])
            if len(labels) > len(best_labels):
                best_labels = labels
                best_page = i
        if best_page is None or len(best_labels) < OVERVIEW_MIN_LABELS:
            return None

        # y'ye göre satırlara kümele (±6pt tolerans), her satırı x'e göre sırala.
        best_labels.sort(key=lambda t: t[1])
        rows: list = []
        for label, y, x in best_labels:
            if rows and abs(y - rows[-1][0]) <= 6.0:
                rows[-1][1].append((label, x))
            else:
                rows.append([y, [(label, x)]])

        grid: dict = {}
        for r, (_, items) in enumerate(rows, start=1):
            for c, (label, _x) in enumerate(sorted(items, key=lambda t: t[1]), start=1):
                grid[label] = (r, c)
        return grid

    @staticmethod
    def _overview_labels(page) -> list:
        lo, hi = OVERVIEW_LABEL_SIZE_RANGE
        out = []
        for block in page.get_text('dict')['blocks']:
            for line in block.get('lines', []):
                for span in line['spans']:
                    t = span['text'].strip()
                    if TILE_NUM_RE.fullmatch(t) and lo <= span['size'] <= hi:
                        out.append((t, round(span['origin'][1]), span['origin'][0]))
        return out

    def parse_product(self, text: str):
        return scan_lexicon(text.lower(), PRODUCT_LEXICON)

    def parse_parts(self, text: str) -> list:
        return []  # Burda parça adlarını karo üstü küçük ok/etiketlerle verir — güvenilir ayrıştırma yok, operatöre bırakılır.

    def parse_size(self, text: str):
        m = SIZE_RE.search(text)
        return f'{m.group(1)}-{m.group(2)}' if m else None

    def match(self, doc) -> float:
        full_text = ''
        giant_pages = 0
        for i in range(doc.page_count):
            page = doc[i]
            full_text += page.get_text('text').lower()
            if self.own_tile_label(page) is not None:
                giant_pages += 1

        score = 0.0
        if 'burda' in full_text:
            score += 0.4
        if giant_pages >= 2:
            score += 0.3
        if giant_pages >= 2 and self.build_grid_map(doc) is not None:
            score += 0.3
        return min(score, 1.0)
