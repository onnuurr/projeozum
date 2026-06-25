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
