import math

import numpy as np

from raster_vectorize import detect_scheme, mask_to_polylines, vectorize_image


def _white(h, w):
    return np.full((h, w, 3), 255, np.uint8)


def _length(poly):
    return sum(math.dist(poly[i], poly[i + 1]) for i in range(len(poly) - 1))


def test_detect_scheme_color():
    img = _white(50, 50)
    img[10:40, 20:23] = (200, 20, 20)  # kırmızı dikey çizgi
    assert detect_scheme(img) == "color"


def test_detect_scheme_mono():
    img = _white(50, 50)
    img[10:40, 20:23] = (20, 20, 20)  # siyah çizgi
    assert detect_scheme(img) == "mono"


def test_detect_scheme_empty():
    assert detect_scheme(_white(30, 30)) == "empty"


def test_mask_to_polylines_line():
    mask = np.zeros((50, 80), bool)
    mask[24:27, 10:70] = True  # kalın yatay çizgi (60px)
    pls = mask_to_polylines(mask, dpi=300, simplify_mm=0.2)
    assert len(pls) >= 1
    longest = max(pls, key=_length)
    # ~60px * 25.4/300 mm ≈ 5.0mm; en az 40px karşılığı bekle
    assert _length(longest) > 40 * 25.4 / 300


def test_vectorize_image_two_colors_two_layers():
    img = _white(90, 90)
    img[10:80, 18:21] = (200, 20, 20)    # kırmızı → kirmizi
    img[10:80, 68:71] = (20, 180, 180)   # cyan → cyan
    polys, meta = vectorize_image(img, dpi=300)
    assert meta["scheme"] == "color"
    assert "kirmizi" in polys and "cyan" in polys


def test_vectorize_image_mono_single_layer():
    img = _white(80, 80)
    img[10:70, 38:41] = (20, 20, 20)
    polys, meta = vectorize_image(img, dpi=300)
    assert meta["scheme"] == "mono"
    assert set(polys.keys()) == {"ortak"}


def test_vectorize_image_empty():
    polys, meta = vectorize_image(_white(40, 40), dpi=300)
    assert meta["scheme"] == "empty"
    assert polys == {}


def _raster_pdf_bytes():
    """Gömülü renkli-çizgili resim içeren sentetik raster PDF (vektör yok)."""
    import io

    import fitz
    from PIL import Image

    img = np.full((400, 400, 3), 255, np.uint8)
    img[50:350, 100:103] = (200, 20, 20)    # kırmızı çizgi
    img[50:350, 300:303] = (20, 180, 180)   # cyan çizgi
    buf = io.BytesIO()
    Image.fromarray(img).save(buf, "PNG")
    doc = fitz.open()
    page = doc.new_page(width=300, height=300)
    page.insert_image(page.rect, stream=buf.getvalue())
    return doc.tobytes()


def test_convert_pdf_routes_raster_to_vectorizer():
    from converter import convert_pdf

    out = convert_pdf(_raster_pdf_bytes(), "synthetic_raster.pdf")
    assert out.classification == "yellow"
    assert out.dxf is not None
    assert "BEDEN_" in out.dxf  # renk→beden katmanı
    assert out.metadata.get("scale_verified") is False


def _vector_pdf_no_profile():
    """Profilsiz vektör PDF: siyah çizgiler, gömülü resim yok (ör. Burda)."""
    import fitz

    doc = fitz.open()
    page = doc.new_page(width=300, height=300)
    shape = page.new_shape()
    shape.draw_line((50, 50), (250, 50))
    shape.draw_line((250, 50), (250, 250))
    shape.finish(color=(0, 0, 0), width=0.6)
    shape.commit()
    return doc.tobytes()


def test_probe_unprofiled_vector_is_vector_not_empty():
    from converter import probe_pdf

    assert probe_pdf(_vector_pdf_no_profile())["kind"] == "vector"


def test_convert_pdf_generic_vector_fallback():
    from converter import convert_pdf

    out = convert_pdf(_vector_pdf_no_profile(), "bryuki.pdf")
    assert out.classification == "yellow"
    assert out.dxf is not None
    assert "KALIP" in out.dxf
    assert out.metadata.get("source") == "vector_generic"
    assert out.metadata.get("scale_verified") is True  # vektör koordinat = gerçek mm
