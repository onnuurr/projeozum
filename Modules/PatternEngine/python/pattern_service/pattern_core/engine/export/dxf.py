"""DXF çıktısı (mimari doküman §4.6).

`$INSUNITS`=4 (mm) zorunlu. Katmanlar ayrı: CUT (kesim hattı = dikiş hattı +
dikiş payı), SEAM (dikiş hattı), NOTCH (kertik — ileri sprint), GRAIN (düz boy),
TEXT (parça adı/beden). Dikiş payı Shapely `buffer(join_style=mitre)` ile
offset olarak üretilir.
"""
from __future__ import annotations

import io

import ezdxf
from shapely import affinity
from shapely.geometry import JOIN_STYLE

from ..geometry import polygon_from_piece
from ...models import GradedPattern, Pattern

_INSUNITS_MM = 4
_SHEET_GAP_MM = 30.0

_LAYERS: dict[str, int] = {
    "CUT": 1,
    "SEAM": 5,
    "NOTCH": 3,
    "GRAIN": 2,
    "TEXT": 7,
}


def _new_doc() -> "ezdxf.document.Drawing":
    doc = ezdxf.new(setup=False)
    doc.header["$INSUNITS"] = _INSUNITS_MM
    for name, color in _LAYERS.items():
        doc.layers.add(name=name, color=color)
    return doc


def _draw_pattern(msp, pattern: Pattern, y_offset: float) -> float:
    """Bir `Pattern`'ın tüm parçalarını `y_offset`'ten başlayarak yan yana çizer.

    Her parça kendi yerel koordinat sisteminde (§4.3) üretilir — DXF sayfasında
    üst üste binmesinler diye yalnızca burada, çizim anında yan yana dizilirler
    (Pattern/Piece veri modeli değişmez, sadece çıktı yerleşimi). Döndürülen
    değer, bir sonraki `Pattern` (gradasyonda bir sonraki beden) için kullanılacak
    y kaydırmasıdır — böylece bedenler üst üste binmeden alt alta dizilir.
    """
    cut_polygons = [
        polygon_from_piece(piece).buffer(piece.seam_allowance, join_style=JOIN_STYLE.mitre)
        for piece in pattern.pieces
    ]
    min_y = min(cp.bounds[1] for cp in cut_polygons)
    max_y = max(cp.bounds[3] for cp in cut_polygons)
    shift_y = y_offset - min_y

    cursor_x = 0.0

    for piece, cut_polygon in zip(pattern.pieces, cut_polygons):
        polygon = polygon_from_piece(piece)
        minx, _, maxx, _ = cut_polygon.bounds
        shift_x = cursor_x - minx

        polygon = affinity.translate(polygon, xoff=shift_x, yoff=shift_y)
        cut_polygon = affinity.translate(cut_polygon, xoff=shift_x, yoff=shift_y)
        cursor_x = maxx + shift_x + _SHEET_GAP_MM

        seam_coords = list(polygon.exterior.coords)
        msp.add_lwpolyline(seam_coords, format="xy", close=True, dxfattribs={"layer": "SEAM"})

        cut_coords = list(cut_polygon.exterior.coords)
        msp.add_lwpolyline(cut_coords, format="xy", close=True, dxfattribs={"layer": "CUT"})

        grain_start = piece.points[piece.grainline[0]]
        grain_end = piece.points[piece.grainline[1]]
        msp.add_line(
            (grain_start.x + shift_x, grain_start.y + shift_y),
            (grain_end.x + shift_x, grain_end.y + shift_y),
            dxfattribs={"layer": "GRAIN"},
        )

        label = polygon.centroid
        msp.add_text(
            f"{piece.name} / {pattern.size}",
            dxfattribs={"layer": "TEXT", "height": 10.0, "insert": (label.x, label.y)},
        )

    return y_offset + (max_y - min_y) + _SHEET_GAP_MM


def export_dxf(pattern: Pattern, out: str) -> str:
    """DXF'i diske yazar, dosya yolunu döner."""
    doc = _new_doc()
    _draw_pattern(doc.modelspace(), pattern, 0.0)
    doc.saveas(out)
    return out


def export_dxf_bytes(pattern: Pattern) -> bytes:
    """DXF'i UTF-8 metin olarak bellekte üretir (API'nin base64 dönmesi için)."""
    doc = _new_doc()
    _draw_pattern(doc.modelspace(), pattern, 0.0)
    stream = io.StringIO()
    doc.write(stream)
    return stream.getvalue().encode("utf-8")


def export_graded_dxf_bytes(graded: GradedPattern) -> bytes:
    """Gradasyon çıktısı (§4.5) — her beden kendi satırında, tek DXF'te.

    Nested/concentric endüstriyel gradasyon çizimi (tüm bedenlerin aynı parça
    üzerinde iç içe eğrilerle gösterilmesi) bu sprint'in kapsamı dışıdır — burada
    her beden kendi tam parça setiyle ayrı bir satırda, okunabilir şekilde yer alır.
    """
    doc = _new_doc()
    msp = doc.modelspace()
    y_offset = 0.0
    for pattern in graded.patterns:
        y_offset = _draw_pattern(msp, pattern, y_offset)

    stream = io.StringIO()
    doc.write(stream)
    return stream.getvalue().encode("utf-8")
