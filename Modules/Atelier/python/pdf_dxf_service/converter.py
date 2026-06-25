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
    "grain":   ("GRAINLINE", 8),
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
        msp.add_line((x1, y1), (x2, y2), dxfattribs={"layer": name})
    for x, y, s in texts:
        msp.add_text(s, height=8, dxfattribs={"layer": LABEL_LAYER, "insert": (x, y)})
    buf = io.StringIO()
    doc.write(buf)
    return buf.getvalue()


def build_dxf_from_polylines(polylines) -> str:
    """mm cinsinden poligonları (izleme çıktısı) DXF'e çevirir. role bilinmiyorsa KALIP."""
    lines = []
    for pl in polylines:
        role = pl.get("role", "cut")
        pts = pl.get("points", [])
        closed = pl.get("closed", True)
        if len(pts) < 2:
            continue
        seq = list(pts)
        if closed and len(pts) >= 3:
            seq = seq + [pts[0]]
        for i in range(len(seq) - 1):
            x1, y1 = seq[i]
            x2, y2 = seq[i + 1]
            lines.append((role, round(float(x1), 3), round(float(y1), 3),
                          round(float(x2), 3), round(float(y2), 3)))
    if not lines:
        raise ValueError("Geçerli poligon yok (kenar üreten en az 2 nokta gerekli).")
    return _build_dxf(lines, [])


def _color_role(rgb) -> str:
    """Vektör path rengi (0..1 RGB) → ROLE_LAYER rolü. Akromatik→ortak, renkli→hue."""
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


def _item_points(it):
    """get_drawings item → ([(x,y)... pt, sayfa-yerel y-aşağı], closed)."""
    k = it[0]
    if k == "l":
        p1, p2 = it[1], it[2]
        return [(p1.x, p1.y), (p2.x, p2.y)], False
    if k == "c":
        p1, c1, c2, p2 = it[1], it[2], it[3], it[4]
        pts = []
        for t in (0.0, 0.25, 0.5, 0.75, 1.0):
            mt = 1.0 - t
            x = mt**3 * p1.x + 3*mt*mt*t * c1.x + 3*mt*t*t * c2.x + t**3 * p2.x
            y = mt**3 * p1.y + 3*mt*mt*t * c1.y + 3*mt*t*t * c2.y + t**3 * p2.y
            pts.append((x, y))
        return pts, False
    if k == "re":
        r = it[1]
        return [(r.x0, r.y0), (r.x1, r.y0), (r.x1, r.y1), (r.x0, r.y1)], True
    return [], False


def vectorize_vector_generic(doc, filename: str = "") -> ConvertOutput:
    """Profilsiz vektör PDF → tüm path'leri renk→rol katmanlı DXF'e döker (Burda vb.).

    Profil-güdümlü hat satıcıyı tanıyamadığında devreye girer. Karo birleştirme/parça
    ayrımı YOK; koordinatlar gerçek (mm) olduğundan ölçek doğrulanmış sayılır. Sayfalar
    yan yana (x-offset) dizilir — operatör CAD'de düzenler.
    """
    polylines = []
    x_off = 0.0
    gap_mm = 20.0
    for i in range(doc.page_count):
        page = doc[i]
        h_mm = page.rect.height * PT_TO_MM
        for path in page.get_drawings():
            role = _color_role(path.get("color"))
            for it in path.get("items", []):
                pts, closed = _item_points(it)
                if len(pts) < 2:
                    continue
                poly = [(round(x * PT_TO_MM + x_off, 3), round(h_mm - y * PT_TO_MM, 3))
                        for x, y in pts]
                polylines.append({"role": role, "points": poly, "closed": closed})
        x_off += page.rect.width * PT_TO_MM + gap_mm

    name = _stem(filename)
    if not polylines:
        return ConvertOutput("red", 0.0, None, {"name": name, "source": "vector_generic"},
                             ["Vektör çizgi bulunamadı."])

    dxf = build_dxf_from_polylines(polylines)
    roles = sorted({p["role"] for p in polylines})
    size_layers = sorted({ROLE_LAYER[r][0] for r in roles
                          if ROLE_LAYER.get(r, ("KALIP",))[0].startswith("BEDEN_")})
    meta = {
        "name": name,
        "source": "vector_generic",
        "size_layers": size_layers,
        "segment_count": len(polylines),
        "scale_verified": True,
        "scale_deviation_mm": None,
    }
    return ConvertOutput(
        "yellow", 50.0, dxf, meta,
        ["Genel vektör çıkarımı (satıcı profili yok) — parça/beden ayrımı operatöre kalır."],
    )


def _stem(filename: str) -> str:
    base = (filename or "kalip").rsplit("/", 1)[-1].rsplit("\\", 1)[-1]
    return base.rsplit(".", 1)[0] or "kalip"


def _is_tile(page, profile) -> bool:
    """Profil is_tile_page() implement etmişse onu kullan; aksi hâlde grid
    profiller için yedek: parse_grid etiket + kabul edilen çizgi var.

    Grid yedek A4 filtresi UYGULAMAZ; boyut sapması _assemble_grid'de ölçülür
    (orijinal davranışla tutarlı: boyutu sapan karo scale_verified=False yaptırır)."""
    if profile.assembly == "contact_sheet":
        return profile.is_tile_page(page)
    # Grid profil için yedek tespit (ör. RuslanProfile is_tile_page tanımlamaz).
    if profile.parse_grid(page.get_text("text")) is None:
        return False
    # En az bir kabul edilen çizgi var mı?
    for path in page.get_drawings():
        if profile.accept_line(path.get("color"), path.get("width") or 0.0):
            return True
    return False


def _collect_tiles(doc, profile):
    """Profile'ın kalıp karosu saydığı sayfalar: (labels, w_mm, h_mm, segments)."""
    tiles = []
    for i in range(doc.page_count):
        page = doc[i]
        if not _is_tile(page, profile):
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
    if not coords:
        return [], {"grid": {"rows": 0, "cols": 0}, "tiles": 0, "scale_verified": False, "scale_deviation_mm": None, "_grid_full": False}, ["Izgara etiketi hiçbir karoda bulunamadı."]
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
    if not tiles:
        return [], [], {"tiles": 0, "scale_verified": False, "scale_deviation_mm": None, "_grid_full": True}, ["Kalıp karosu yok."]
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

    # Raster (taranmış) PDF → otomatik vektörleştirme çekirdeği. Hiç vektör çizim yok
    # ama gömülü resim varsa profil-güdümlü vektör hattı yerine raster hattına yönlendir.
    has_vectors = any(len(doc[i].get_drawings()) > 0 for i in range(doc.page_count))
    has_images = any(doc[i].get_images() for i in range(doc.page_count))
    if not has_vectors and has_images:
        from raster_vectorize import vectorize_raster
        return vectorize_raster(data, filename)

    profile, score, candidates = detect_profile(doc)
    if profile is None:
        # Profil yok ama vektör çizim varsa → genel vektör çıkarımı (Burda vb. profilsiz).
        if has_vectors:
            return vectorize_vector_generic(doc, filename)
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
    has_vectors = any(len(doc[i].get_drawings()) > 0 for i in range(n))
    if profile is not None:
        tiles = _collect_tiles(doc, profile)
        if tiles:
            return {"kind": "vector_tiled", "pages": n, "profile": profile.name,
                    "score": score, "tiles": len(tiles)}
        return {"kind": "vector", "pages": n, "profile": profile.name, "score": score}
    # Profil yok ama vektör çizim var → 'vector' (genel çıkarımla işlenir; 'empty' diye atlanmaz).
    if has_vectors:
        return {"kind": "vector", "pages": n, "profile": None, "unprofiled": True,
                "profile_candidates": candidates}
    if img_pages > 0:
        return {"kind": "raster", "pages": n, "image_pages": img_pages,
                "profile_candidates": candidates}
    return {"kind": "empty", "pages": n}
