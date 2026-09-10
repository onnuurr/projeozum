import ezdxf

from pattern_core.engine.export.dxf import export_dxf_bytes, export_graded_dxf_bytes
from pattern_core.models import DesignParams
from pattern_core.pipeline import generate, generate_graded


def _generate_8y():
    pattern, _ = generate("tshirt", "8Y", "ts_default", DesignParams())
    return pattern


def test_dxf_insunits_is_mm(tmp_path):
    pattern = _generate_8y()
    dxf_bytes = export_dxf_bytes(pattern)

    out = tmp_path / "tshirt_8y.dxf"
    out.write_bytes(dxf_bytes)

    doc = ezdxf.readfile(str(out))
    assert doc.header["$INSUNITS"] == 4


def test_dxf_has_expected_layers_and_one_entity_set_per_piece(tmp_path):
    pattern = _generate_8y()
    dxf_bytes = export_dxf_bytes(pattern)

    out = tmp_path / "tshirt_8y.dxf"
    out.write_bytes(dxf_bytes)

    doc = ezdxf.readfile(str(out))
    layer_names = {layer.dxf.name for layer in doc.layers}
    assert {"CUT", "SEAM", "NOTCH", "GRAIN", "TEXT"}.issubset(layer_names)

    msp = doc.modelspace()
    piece_count = len(pattern.pieces)
    assert len(list(msp.query("LWPOLYLINE[layer=='SEAM']"))) == piece_count
    assert len(list(msp.query("LWPOLYLINE[layer=='CUT']"))) == piece_count
    assert len(list(msp.query("LINE[layer=='GRAIN']"))) == piece_count
    assert len(list(msp.query("TEXT[layer=='TEXT']"))) == piece_count


def test_dxf_pieces_do_not_overlap_on_sheet(tmp_path):
    """Sayfa yerleşimi (§4.6) parçaları yan yana dizmeli — CUT poligonları kesişmemeli."""
    from shapely.geometry import Polygon

    pattern = _generate_8y()
    dxf_bytes = export_dxf_bytes(pattern)

    out = tmp_path / "tshirt_8y.dxf"
    out.write_bytes(dxf_bytes)

    doc = ezdxf.readfile(str(out))
    msp = doc.modelspace()
    cut_polygons = [
        Polygon([(p[0], p[1]) for p in entity.get_points()])
        for entity in msp.query("LWPOLYLINE[layer=='CUT']")
    ]

    for i in range(len(cut_polygons)):
        for j in range(i + 1, len(cut_polygons)):
            assert not cut_polygons[i].overlaps(cut_polygons[j])


def test_graded_dxf_has_one_row_per_size_without_overlap(tmp_path):
    from shapely.geometry import Polygon

    graded, _ = generate_graded("tshirt", "8Y", ["4Y", "12Y"], "ts_default", DesignParams())
    dxf_bytes = export_graded_dxf_bytes(graded)

    out = tmp_path / "tshirt_graded.dxf"
    out.write_bytes(dxf_bytes)

    doc = ezdxf.readfile(str(out))
    assert doc.header["$INSUNITS"] == 4

    msp = doc.modelspace()
    total_pieces = sum(len(p.pieces) for p in graded.patterns)
    assert len(list(msp.query("LWPOLYLINE[layer=='CUT']"))) == total_pieces
    assert len(list(msp.query("TEXT[layer=='TEXT']"))) == total_pieces

    cut_polygons = [
        Polygon([(p[0], p[1]) for p in entity.get_points()])
        for entity in msp.query("LWPOLYLINE[layer=='CUT']")
    ]
    for i in range(len(cut_polygons)):
        for j in range(i + 1, len(cut_polygons)):
            assert not cut_polygons[i].overlaps(cut_polygons[j])
