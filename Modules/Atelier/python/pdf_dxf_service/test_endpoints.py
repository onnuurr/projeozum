from fastapi.testclient import TestClient
from main import app

client = TestClient(app)


def test_build_dxf_closed_square_has_four_segments():
    # 100x100 mm kapalı kesim konturu → DXF + 4 LINE + KALIP katmanı
    square = [[0, 0], [100, 0], [100, 100], [0, 100]]
    resp = client.post("/build-dxf", json={
        "polylines": [{"role": "cut", "points": square, "closed": True}]
    })
    assert resp.status_code == 200
    dxf = resp.json()["dxf"]
    assert "KALIP" in dxf
    assert dxf.count("\nLINE\n") == 4  # kapalı → 4 kenar


def test_build_dxf_rejects_degenerate():
    resp = client.post("/build-dxf", json={
        "polylines": [{"role": "cut", "points": [[0, 0]], "closed": True}]
    })
    assert resp.status_code == 422


import fitz


def _sample_pdf_bytes(pages=2):
    doc = fitz.open()
    for _ in range(pages):
        doc.new_page(width=595, height=842)  # A4 pt
    return doc.tobytes()


def test_render_returns_png_with_page_count():
    pdf = _sample_pdf_bytes(pages=3)
    resp = client.post("/render", files={"file": ("p.pdf", pdf, "application/pdf")},
                       data={"page": 0, "dpi": 150})
    assert resp.status_code == 200
    assert resp.headers["content-type"] == "image/png"
    assert resp.headers["x-page-count"] == "3"
    assert resp.content[:8] == b"\x89PNG\r\n\x1a\n"


def test_render_out_of_range_page():
    pdf = _sample_pdf_bytes(pages=1)
    resp = client.post("/render", files={"file": ("p.pdf", pdf, "application/pdf")},
                       data={"page": 5})
    assert resp.status_code == 422
