"""Raster (taranmış) kalıp PDF'lerini otomatik vektörleştirme — klasik çekirdek.

Hedef "Illustrator Image Trace" seviyesi: render → renk/şema segmentasyonu →
orta-çizgi (skeleton) vektörleştirme → beden-renk katmanlı DXF. Parça isimlendirme,
kesin ölçek ve dash-stili beden ayrımı OTOMATİK DEĞİL — operatör tamamlar (tracer).

Renk-kodlu taramalarda her renk bir bedendir (mevcut ROLE_LAYER karşılığı). Renksiz
taramalarda tek katman (ortak) üretilir. Hiç çizgi yoksa 'red' → çağıran tracer'a düşürür.
"""

from __future__ import annotations

import numpy as np
from skimage.morphology import skeletonize

# Hue (0..360) → ROLE_LAYER rolü referansları. Dergi beden renkleri hue'da uzak durur.
HUE_ROLES = [(0.0, "kirmizi"), (60.0, "sari"), (120.0, "yesil"),
             (180.0, "cyan"), (240.0, "mavi"), (300.0, "mor")]

DARK_MAX = 0.85       # mx < DARK_MAX → koyu (çizgi) piksel
CHROMA_MIN = 0.18     # chroma > CHROMA_MIN → renkli (akromatik değil)
COLOR_FRAC = 0.15     # koyu piksellerin bu oranı renkliyse → 'color' şeması
MIN_BAND_PX = 40      # bir renk bandı en az bu kadar piksel içermeli
MIN_POLY_PX = 8       # bu uzunluktan kısa polyline'lar (spur) atılır


def _channels(rgb: np.ndarray):
    a = rgb.astype(float) / 255.0
    return a, a.max(2), a.min(2)


def _hue(rgb: np.ndarray) -> np.ndarray:
    a, mx, mn = _channels(rgb)
    r, g, b = a[:, :, 0], a[:, :, 1], a[:, :, 2]
    d = mx - mn
    d_safe = np.where(d == 0, 1.0, d)
    maxc = a.argmax(2)
    h = np.zeros_like(mx)
    h = np.where(maxc == 0, ((g - b) / d_safe) % 6, h)
    h = np.where(maxc == 1, ((b - r) / d_safe) + 2, h)
    h = np.where(maxc == 2, ((r - g) / d_safe) + 4, h)
    return (h * 60.0) % 360.0


def _masks(rgb: np.ndarray):
    """(dark, chroma) — koyu çizgi maskesi ve renklilik."""
    _, mx, mn = _channels(rgb)
    return mx < DARK_MAX, (mx - mn)


def detect_scheme(rgb: np.ndarray) -> str:
    """'color' | 'mono' | 'empty' — çizgilerin nasıl ayrıldığını tespit eder."""
    dark, chroma = _masks(rgb)
    n = int(dark.sum())
    if n == 0:
        return "empty"
    colored = dark & (chroma > CHROMA_MIN)
    return "color" if (colored.sum() / n) >= COLOR_FRAC else "mono"


def _hue_role_index(hue: np.ndarray) -> np.ndarray:
    refs = np.array([h for h, _ in HUE_ROLES])
    h = hue[..., None]
    dd = np.minimum(np.abs(h - refs), 360.0 - np.abs(h - refs))
    return dd.argmin(-1)


def segment_bands(rgb: np.ndarray) -> dict:
    """Renk stratejisi: {rol: bool-maske}. Akromatik koyu → 'ortak'; renkliler hue→rol."""
    dark, chroma = _masks(rgb)
    colored = dark & (chroma > CHROMA_MIN)
    bands: dict = {}

    achrom = dark & ~colored
    if int(achrom.sum()) >= MIN_BAND_PX:
        bands["ortak"] = achrom

    if int(colored.sum()) > 0:
        idx = _hue_role_index(_hue(rgb))
        for i, (_, role) in enumerate(HUE_ROLES):
            m = colored & (idx == i)
            if int(m.sum()) >= MIN_BAND_PX:
                bands[role] = m
    return bands


def mask_to_polylines(mask: np.ndarray, dpi: int = 300,
                      simplify_mm: float = 0.5) -> list:
    """İkili maske → orta-çizgi polyline'ları (mm, DXF y-yukarı). skeleton→graph→sadeleştir."""
    import sknw
    from shapely.geometry import LineString

    skel = skeletonize(mask > 0)
    if not skel.any():
        return []

    graph = sknw.build_sknw(skel)
    mm = 25.4 / dpi
    h = mask.shape[0]
    min_len_mm = MIN_POLY_PX * mm
    out = []
    for s, e in graph.edges():
        pts = graph[s][e]["pts"]  # Nx2 [row, col]
        if len(pts) < 2:
            continue
        poly = [(float(c) * mm, float(h - r) * mm) for r, c in pts]
        line = LineString(poly).simplify(simplify_mm)
        coords = [(round(x, 3), round(y, 3)) for x, y in line.coords]
        if len(coords) >= 2 and line.length >= min_len_mm:
            out.append(coords)
    return out


def vectorize_image(rgb: np.ndarray, dpi: int = 300, simplify_mm: float = 0.5):
    """Tek görüntü → ({rol: [polyline...]}, meta). Şema tespiti + segmentasyon + vektörleştirme."""
    scheme = detect_scheme(rgb)
    if scheme == "empty":
        return {}, {"scheme": "empty"}

    if scheme == "mono":
        dark, _ = _masks(rgb)
        bands = {"ortak": dark}
    else:
        bands = segment_bands(rgb)

    polys: dict = {}
    total = 0
    for role, mask in bands.items():
        pls = mask_to_polylines(mask, dpi, simplify_mm)
        if pls:
            polys[role] = pls
            total += len(pls)

    return polys, {"scheme": scheme, "bands": sorted(polys.keys()), "polyline_count": total}


def vectorize_raster(data: bytes, filename: str = "", dpi: int = 300):
    """PDF bytes → ConvertOutput. Sayfaları render eder, vektörleştirir, çok katmanlı DXF üretir.

    Sayfalar yan yana (x-offset) dizilir; karo birleştirme v1 kapsamı dışı.
    """
    import fitz

    from converter import ROLE_LAYER, ConvertOutput, _stem, build_dxf_from_polylines

    doc = fitz.open(stream=data, filetype="pdf")
    all_polylines = []
    x_offset = 0.0
    gap_mm = 20.0
    schemes = set()

    for i in range(doc.page_count):
        pix = doc[i].get_pixmap(dpi=dpi)
        rgb = np.frombuffer(pix.samples, np.uint8).reshape(pix.height, pix.width, pix.n)[:, :, :3]
        polys, meta = vectorize_image(rgb, dpi)
        schemes.add(meta["scheme"])
        for role, pls in polys.items():
            for pl in pls:
                pts = [(round(x + x_offset, 3), y) for x, y in pl]
                all_polylines.append({"role": role, "points": pts, "closed": False})
        x_offset += pix.width * 25.4 / dpi + gap_mm

    name = _stem(filename)
    if not all_polylines:
        return ConvertOutput("red", 0.0, None, {"name": name, "source": "raster"},
                             ["Raster vektörleştirme: çizgi bulunamadı (boş/okunamayan tarama)."])

    try:
        dxf = build_dxf_from_polylines(all_polylines)
    except ValueError as exc:
        return ConvertOutput("red", 0.0, None, {"name": name, "source": "raster"}, [str(exc)])

    roles = sorted({p["role"] for p in all_polylines})
    size_layers = sorted({ROLE_LAYER[r][0] for r in roles
                          if ROLE_LAYER.get(r, ("KALIP",))[0].startswith("BEDEN_")})
    meta = {
        "name": name,
        "source": "raster",
        "scheme": "color" if "color" in schemes else "mono",
        "size_layers": size_layers,
        "segment_count": len(all_polylines),
        "scale_verified": False,
        "scale_deviation_mm": None,
    }
    return ConvertOutput(
        "yellow", 45.0, dxf, meta,
        ["Otomatik raster vektörleştirme — ölçek ve parça ayrımı operatör tarafından doğrulanmalı."],
    )
