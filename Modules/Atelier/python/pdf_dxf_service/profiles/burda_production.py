"""Burda kalıp PDF'inden ÜRETİM bilgisi: bedenler, varyantlar, parça/kesim listesi,
kumaş sarfiyatı, dikiş payı ve vücut ölçü tablosu (Kalıp Kartı — 2026-10-07).

Hepsi en-iyi-çaba: okunamayan alan anahtarı hiç döndürülmez (PHP tarafı mevcut/elle
girilmiş değeri korur). Dil değişebilir (talimat sayfası alıcının dilinde: Rusça,
Almanca…) — ayrıştırma kelimelere değil YAPIYA dayanır:

* Sarfiyat tablosu: "Eur." satırında beden başlığı (kumaş eni başına bir kez tekrar
  eder: 114 cm / 140 cm), altında "A I  m 1,25 1,30 …" satırları (varyant + kumaş kodu).
* Parça listesi: "A B 6 Рукав 2x" — varyant harfleri + parça no + ad + "Nx" adedi.
* Vücut tablosu: kalıp karolarına BÖLÜNMÜŞ basılır (gerçek vaka Mod.9301: sayfa 8, 9,
  17 — ve 90° döndürülmüş). Kelimeler birleştirilmiş ızgara koordinatına taşınır
  (karo konumu build_grid_map'ten), "Eur" çapasından bedenler, 1..8 numaralı sütun
  etiketlerinden ölçü kolonları bulunur; numaraların anlamı lejanttan ("1 Körpergröße").
"""

from __future__ import annotations

import re
from statistics import median

SIZE_RE = re.compile(r'^\d{2,3}$')
DEC_RE = re.compile(r'^\d{1,3}(?:[.,]\d{1,2})?$')
ROMAN_RE = re.compile(r'^(I|II|III|IV|V)$')
QTY_RE = re.compile(r'^(\d{1,2})[xх×]$', re.IGNORECASE)  # Latin x / Kiril х / ×
SEAM_RE = re.compile(r'(\d+(?:[.,]\d)?)\s*[cс][mм]', re.IGNORECASE)
SEAM_KEYS = ('nahtzugabe', 'seam allowance', 'припуск', 'nahtzugaben', 'marges de couture')

# Burda vücut tablosu lejantı → Kalıp Ayarları ölçü noktası anahtarı (DE/EN/FR/NL anahtar kelime).
LEGEND_KEYS = [
    ('height', ('körpergröße', 'height', 'stature', 'lichaamslengte')),
    ('chest_circ', ('brustumfang', 'bust', 'chest', 'tour de poitrine', 'bovenwijdte')),
    ('waist_circ', ('taillenumfang', 'waist', 'tour de taille', 'taillenwijdte')),
    ('hip_circ', ('hüftumfang', 'hip', 'tour des hanches', 'heupwijdte')),
    ('arm_length', ('armlänge', 'sleeve length', 'longueur de manche', 'mouwlengte')),
    ('inseam', ('innere beinlänge', 'inside leg', 'longueur interne', 'binnenbeenlengte')),
    ('side_length', ('seitenlänge', 'side leg', 'longueur côté', 'zijlengte')),
    ('back_length', ('rückenlänge', 'back length', 'longueur du dos', 'ruglengte')),
]
# Lejant bulunamazsa Burda'nın standart numaralaması.
DEFAULT_LEGEND = {1: 'height', 2: 'chest_circ', 3: 'waist_circ', 4: 'hip_circ',
                  5: 'arm_length', 6: 'side_length', 7: 'inseam', 8: 'back_length'}
GROUP_WORDS = {'mädchen': 'Kız', 'girl': 'Kız', 'junge': 'Erkek', 'boy': 'Erkek',
               'damen': 'Kadın', 'women': 'Kadın', 'herren': 'Erkek', 'men': 'Erkek'}


def _num(text: str) -> float | None:
    t = text.strip().replace(',', '.')
    try:
        return float(t)
    except ValueError:
        return None


def _cx(w):
    return (w[0] + w[2]) / 2


def _cy(w):
    return (w[1] + w[3]) / 2


def _rows(words, tol=3.0):
    """Kelimeleri y-merkezine göre satırlara grupla → [[w,...], ...] (x sıralı)."""
    rows: list = []
    for w in sorted(words, key=_cy):
        if rows and abs(_cy(w) - _cy(rows[-1][0])) <= tol:
            rows[-1].append(w)
        else:
            rows.append([w])
    return [sorted(r, key=_cx) for r in rows]


# ── sarfiyat ─────────────────────────────────────────────────────────────────

def parse_fabric_usage(doc) -> tuple[list, dict | None]:
    """→ (bedenler, fabric_usage). Tablo yoksa ([], None)."""
    for i in range(doc.page_count):
        page = doc[i]
        words = page.get_text('words')
        rows = _rows(words)
        for ri, row in enumerate(rows):
            eur = next((w for w in row if w[4].rstrip('.').lower() == 'eur'), None)
            if eur is None:
                continue
            header = [w for w in row if SIZE_RE.match(w[4]) and w[0] > eur[0]]
            if len(header) < 3:
                continue
            groups = _split_repeats(header)
            sizes = [w[4] for w in groups[0]]

            widths = _width_labels(rows[max(0, ri - 3):ri], groups)
            fabrics: list = []
            out_rows: list = []
            for row2 in rows[ri + 1: ri + 15]:
                lead = [w for w in row2 if w[0] < header[0][0] - 2]
                # Kumaş kodu da büyük harf (I/II/III) — Roma rakamları varyanttan hariç.
                variant = ''.join(w[4] for w in lead if re.fullmatch(r'[A-Z]{1,2}', w[4]) and not ROMAN_RE.match(w[4]))
                fabric = next((w[4] for w in lead if ROMAN_RE.match(w[4])), None)
                if not variant or not fabric:
                    continue
                for gi, grp in enumerate(groups):
                    values = {}
                    for hw in grp:
                        cell = _nearest(row2, _cx(hw), 9)
                        v = _num(cell[4]) if cell else None
                        if v is not None:
                            values[hw[4]] = v
                    if values:
                        out_rows.append({'variant': variant, 'fabric': fabric,
                                         'width_cm': widths[gi] if gi < len(widths) else None,
                                         'values': values})
                if fabric not in fabrics:
                    fabrics.append(fabric)
            if out_rows:
                return sizes, {'unit': 'm', 'fabrics': [{'code': f, 'name': None} for f in fabrics], 'rows': out_rows}
    return [], None


def _split_repeats(header):
    """Beden başlığı tekrar ediyorsa (her kumaş eni için) gruplara böl."""
    groups = [[header[0]]]
    for w in header[1:]:
        if w[4] in {x[4] for x in groups[-1]}:
            groups.append([w])
        else:
            groups[-1].append(w)
    return groups


def _width_labels(rows_above, groups) -> list:
    """Başlığın üstündeki "114 cm" / "140 cm" etiketlerini x-aralığına göre gruplara eşle."""
    cands = []
    for row in rows_above:
        for j, w in enumerate(row):
            nxt = row[j + 1][4].lower() if j + 1 < len(row) else ''
            if re.fullmatch(r'\d{2,3}', w[4]) and nxt in ('cm', 'см'):
                cands.append((_cx(w), int(w[4])))
    widths = []
    for grp in groups:
        lo, hi = grp[0][0] - 10, grp[-1][2] + 10
        inside = [v for x, v in cands if lo <= x <= hi]
        widths.append(inside[0] if inside else None)
    return widths


def _nearest(row, x, tol):
    best = min(row, key=lambda w: abs(_cx(w) - x), default=None)
    return best if best is not None and abs(_cx(best) - x) <= tol else None


# ── parça listesi ────────────────────────────────────────────────────────────

def parse_parts(doc) -> list:
    """"A B 6 Рукав 2x" satırları → [{part_no, part_name, quantity, variants}]."""
    parts: list = []
    seen: set = set()
    for i in range(min(doc.page_count, 6)):
        for row in _rows(doc[i].get_text('words'), tol=3.5):
            for qi, qw in enumerate(row):
                m = QTY_RE.match(qw[4])
                if not m:
                    continue
                # qty'den sola: ad kelimeleri → parça no (salt sayı) → varyant harfleri
                j = qi - 1
                name: list = []
                while j >= 0 and not re.fullmatch(r'\d{1,2}', row[j][4]) and qw[0] - row[j][2] < 160:
                    name.insert(0, row[j][4])
                    j -= 1
                if j < 0 or not name or not re.fullmatch(r'\d{1,2}', row[j][4]):
                    continue
                no = row[j][4]
                variants: list = []
                k = j - 1
                while k >= 0 and re.fullmatch(r'[A-Z]', row[k][4]) and row[k + 1][0] - row[k][2] < 25:
                    variants.insert(0, row[k][4])
                    k -= 1
                key = (no, ' '.join(name))
                if key in seen:
                    continue
                seen.add(key)
                parts.append({'part_no': no, 'part_name': ' '.join(name)[:100],
                              'quantity': int(m.group(1)), 'variants': ','.join(variants) or None})
    return parts


# ── dikiş payı ───────────────────────────────────────────────────────────────

def parse_seam_allowance(doc) -> float | None:
    for i in range(min(doc.page_count, 6)):
        text = doc[i].get_text('text')
        low = text.lower()
        for key in SEAM_KEYS:
            pos = low.find(key)
            if pos < 0:
                continue
            m = SEAM_RE.search(text[pos: pos + 200])
            if m:
                v = _num(m.group(1))
                if v is not None and 0 < v < 10:
                    return v
    return None


# ── vücut ölçü tablosu ───────────────────────────────────────────────────────

def _global_words(doc, profile) -> list:
    """Karo sayfalarının kelimeleri birleştirilmiş ızgara koordinatında: (x0,y0,x1,y1,metin)."""
    grid_map = profile.build_grid_map(doc)
    if not grid_map:
        return []
    out = []
    for i in range(doc.page_count):
        page = doc[i]
        label = profile.own_tile_label(page)
        if label is None or label not in grid_map:
            continue
        r, c = grid_map[label]
        ox, oy = (c - 1) * page.rect.width, (r - 1) * page.rect.height
        for w in page.get_text('words'):
            out.append((w[0] + ox, w[1] + oy, w[2] + ox, w[3] + oy, w[4]))
    return out


def _legend(doc) -> dict:
    """"1 Körpergröße" satırlarından numara → ölçü anahtarı."""
    legend: dict = {}
    for i in range(doc.page_count):
        for line in doc[i].get_text('text').splitlines():
            m = re.match(r'^\s*(\d)\s+(.+)$', line)
            if not m:
                continue
            low = m.group(2).lower()
            for key, words in LEGEND_KEYS:
                if any(w in low for w in words):
                    legend.setdefault(int(m.group(1)), key)
                    break
    return legend or DEFAULT_LEGEND


def parse_body_measurements(doc, profile, sizes: list) -> list:
    words = _global_words(doc, profile)
    if not words:
        return []
    legend = _legend(doc)
    tables = []
    for eur in [w for w in words if w[4].rstrip('.').lower() == 'eur']:
        for rotated in (True, False):
            t = _read_table(words, eur, rotated, legend, sizes)
            if t:
                tables.extend(t)
                break
    return tables


def _read_table(words, eur, rotated, legend, wanted_sizes):
    # Döndürülmüş tabloda eksenleri değiştir: bedenler hep "dikey" (along) eksende kalsın.
    def along(w):
        return _cy(w) if rotated else _cx(w)

    def across(w):
        return _cx(w) if rotated else _cy(w)

    ea, ex = along(eur), across(eur)
    # Bedenler: Eur ile aynı "across" hizasında, salt 2-3 haneli sayılar; Eur'dan uzaklaştıkça
    # ardışık (büyük boşlukta tablo biter).
    col = sorted((w for w in words if w is not eur and abs(across(w) - ex) <= 4 and SIZE_RE.match(w[4])),
                 key=lambda w: abs(along(w) - ea))
    sizes = []
    last = ea
    for w in col:
        if abs(along(w) - last) > 60:
            break
        sizes.append(w)
        last = along(w)
    if len(sizes) < 4:
        return None

    # Sütun etiketleri 1..8: Eur'a yakın (along ±60), tek haneli, aynı "along" hizasında.
    labels = [w for w in words if re.fullmatch(r'\d', w[4]) and abs(along(w) - ea) <= 60
              and 0 < (across(w) - ex) <= 260]
    if len(labels) < 2:
        return None
    line = median(along(w) for w in labels)
    labels = sorted((w for w in labels if abs(along(w) - line) <= 4), key=across)
    if len(labels) < 2:  # aralık (gap) hesabı için en az iki sütun
        return None
    gap = median(across(b) - across(a) for a, b in zip(labels, labels[1:]))

    group = None
    for w in words:
        g = GROUP_WORDS.get(w[4].strip().lower())
        if g and abs(across(w) - ex) <= 40 and abs(along(w) - ea) <= 80:
            group = g
            break

    result = []
    for lw in labels:
        key = legend.get(int(lw[4]))
        if not key:
            continue
        # cm kolonu etiketin "önünde" (yarım aralık geri); ins kolonu arkasında — yalnız cm okunur.
        cm_x = across(lw) - gap / 4
        values = {}
        for sw in sizes:
            if wanted_sizes and sw[4] not in wanted_sizes:
                continue
            cell = min((w for w in words if abs(along(w) - along(sw)) <= 6 and abs(across(w) - cm_x) <= gap / 4
                        and DEC_RE.match(w[4])), key=lambda w: abs(across(w) - cm_x), default=None)
            v = _num(cell[4]) if cell else None
            if v is not None:
                values[sw[4]] = v
        if values:
            result.append({'basis': 'body', 'group': group, 'point_key': key, 'values': values})
    return result or None


def parse_production(doc, profile) -> dict:
    out: dict = {}
    sizes, fabric = parse_fabric_usage(doc)
    if sizes:
        out['sizes'] = sizes
        out['size_range'] = f'{sizes[0]}-{sizes[-1]}'
    if fabric:
        out['fabric_usage'] = fabric
    parts = parse_parts(doc)
    if parts:
        out['parts'] = parts
    codes = sorted({v for p in parts for v in (p['variants'] or '').split(',') if v}
                   | {r['variant'] for r in (fabric or {}).get('rows', []) if len(r['variant']) == 1})
    if codes:
        out['variants'] = [{'code': c, 'name': None} for c in codes]
    seam = parse_seam_allowance(doc)
    if seam is not None:
        out['seam_allowance_cm'] = seam
    body = parse_body_measurements(doc, profile, sizes)
    if body:
        out['body_measurements'] = body
    return out
