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
        """Maßtabelle'yi beden-kolonu x-çapalarıyla kur (Sprungwerte ayıklanır).

        Sayfada beden başlık satırı (≥15 adet 2-3 haneli beden) bulunur; başlığın
        x-merkezleri kolon çapasıdır. Her ölçü etiketi (OW/TW/HW/SL/KH) için başlık
        ALTINDAKİ ilk veri satırı (legend değil) seçilir; her çapaya en yakın sayı
        o bedenin ölçüsüdür — atlama değerleri (Sprungwerte) çapa-dışı x'te kalıp
        elenir. Tablo konumsal/karışıksa None döner (operatör doğrular).
        """
        def cx(w):
            return (w[0] + w[2]) / 2

        num_re = re.compile(r"\d+[.,]?\d*")
        size_re = re.compile(r"\d{2,3}")
        tol = 9.0

        for i in range(min(doc.page_count, 8)):
            words = doc[i].get_text("words")  # (x0,y0,x1,y1,word,...)
            if not words:
                continue

            # y-bandlarına grupla; başlık = en çok 2-3 haneli beden taşıyan band (≥15).
            bands: dict = {}
            for w in words:
                bands.setdefault(round(w[1]), []).append(w)

            def size_words(ws):
                return sorted((w for w in ws if size_re.fullmatch(w[4])), key=cx)

            hdr_y = max(
                bands,
                key=lambda y: len(size_words(bands[y])) if len(size_words(bands[y])) >= 15 else 0,
            )
            header = size_words(bands[hdr_y])
            if len(header) < 15:
                continue
            sizes = [w[4] for w in header]
            anchors = [cx(w) for w in header]

            # Etiketin başlık ALTINDAKİ ilk (en küçük y) veri satırı = ana tablo.
            def data_row_y(label):
                cands = [
                    w[1]
                    for w in words
                    if w[4] == label and w[0] < 90 and w[1] > hdr_y
                    and sum(1 for ww in words if abs(ww[1] - w[1]) < 4 and num_re.fullmatch(ww[4])) >= 15
                ]
                return min(cands) if cands else None

            matrix: dict = {}
            for label in ("OW", "TW", "HW", "SL", "KH"):
                y = data_row_y(label)
                if y is None:
                    continue
                # Satır sayıları (etiket-indeksini dışla: ilk çapanın solunda kalır).
                row = [
                    w for w in words
                    if abs(w[1] - y) < 4 and num_re.fullmatch(w[4]) and cx(w) > anchors[0] - 12
                ]
                if not row:
                    continue
                vals = []
                for a in anchors:
                    best = min(row, key=lambda w: abs(cx(w) - a))
                    vals.append(float(best[4].replace(",", ".")) if abs(cx(best) - a) <= tol else None)
                # Kolonların çoğu hizalanmalı; aksi halde bu satır güvenilmez.
                if sum(v is not None for v in vals) >= 0.8 * len(anchors):
                    matrix[label] = vals

            if len({"OW", "TW", "HW"} & set(matrix)) >= 3:
                return {
                    "labels": list(matrix.keys()),
                    "sizes": sizes,
                    "matrix": matrix,
                    "unit": "cm",
                    "note": "Modellmasse ohne Nahtzugabe; beden-kolonu hizalı, operatör doğrular",
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
