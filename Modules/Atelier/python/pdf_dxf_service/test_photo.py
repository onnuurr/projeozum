import cv2
import numpy as np

from photo_vectorize import DEFAULT_MARKER_MM, detect_marker, dewarp_and_scale, vectorize_photo

PX_PER_MM_SRC = 6.0  # sentetik "fotoğraf" üretim çözünürlüğü (marker'ın gerçek boyutunu simüle eder)


def _synthetic_photo(rotate: bool = False):
    """Beyaz zemin + ArUco marker + kırmızı bir 'kesim çizgisi' — dik açıdan (perspektifsiz),
    yalnızca ölçek varsayımını test eder (asıl warp perspektif-DÜZELTMEYİ değil,
    marker bulunca doğru px/mm çıkarmayı ve pipeline'ı doğrular)."""
    h_mm, w_mm = 150, 200
    h, w = int(h_mm * PX_PER_MM_SRC), int(w_mm * PX_PER_MM_SRC)
    img = np.full((h, w, 3), 255, np.uint8)

    marker_px = int(DEFAULT_MARKER_MM * PX_PER_MM_SRC)
    aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_4X4_50)
    marker_img = cv2.aruco.generateImageMarker(aruco_dict, 0, marker_px)
    marker_bgr = cv2.cvtColor(marker_img, cv2.COLOR_GRAY2BGR)
    my, mx = 80, 80  # kenardan uzak — rotate=True testinde döndürme marker'ı kırpmasın
    img[my:my + marker_px, mx:mx + marker_px] = marker_bgr

    # Kırmızı dikey "kesim çizgisi", marker'dan uzakta, aynı düzlemde.
    x0 = mx + marker_px + 100
    img[40:h - 40, x0:x0 + 4] = (200, 20, 20)

    if rotate:
        center = (w / 2, h / 2)
        # Not: sentetik marker'ın keskin (blur'suz) kenarları ~13° üstü döndürmede
        # ArUco'nun köşe alt-piksel arıtmasını bozuyor (gerçek fotoğraflarda lens
        # bulanıklığı/baskı kenar geçişi bu sorunu yaratmaz) — testte ölçülü bir açı.
        rot = cv2.getRotationMatrix2D(center, 10, 1.0)
        img = cv2.warpAffine(img, rot, (w, h), borderValue=(255, 255, 255))

    ok, buf = cv2.imencode(".png", cv2.cvtColor(img, cv2.COLOR_RGB2BGR))
    assert ok
    return buf.tobytes()


def test_detect_marker_finds_corners():
    data = _synthetic_photo()
    arr = np.frombuffer(data, np.uint8)
    bgr = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

    corners = detect_marker(rgb)
    assert corners is not None
    assert corners.shape == (4, 2)


def test_detect_marker_none_without_marker():
    img = np.full((200, 200, 3), 255, np.uint8)
    assert detect_marker(img) is None


def test_dewarp_and_scale_recovers_known_size():
    data = _synthetic_photo()
    arr = np.frombuffer(data, np.uint8)
    bgr = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

    warped, px_per_mm = dewarp_and_scale(rgb, marker_mm=DEFAULT_MARKER_MM)
    assert warped is not None
    assert px_per_mm > 0
    # Marker kenarı warp sonrası tam olarak marker_mm * px_per_mm px olmalı.
    expected_marker_px = DEFAULT_MARKER_MM * px_per_mm
    corners = detect_marker(warped)
    assert corners is not None
    side = np.linalg.norm(corners[0] - corners[1])
    assert abs(side - expected_marker_px) < 3.0


def test_dewarp_handles_rotated_marker():
    data = _synthetic_photo(rotate=True)
    arr = np.frombuffer(data, np.uint8)
    bgr = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

    warped, px_per_mm = dewarp_and_scale(rgb, marker_mm=DEFAULT_MARKER_MM)
    assert warped is not None
    assert px_per_mm > 0


def test_vectorize_photo_without_marker_is_red_with_actionable_error():
    img = np.full((200, 200, 3), 255, np.uint8)
    img[40:160, 90:94] = (20, 20, 20)
    ok, buf = cv2.imencode(".png", img)
    out = vectorize_photo(buf.tobytes(), "test.png")

    assert out.classification == "red"
    assert out.dxf is None
    assert any("marker" in e.lower() for e in out.errors)


def test_vectorize_photo_with_marker_and_line_produces_scaled_dxf():
    data = _synthetic_photo()
    out = vectorize_photo(data, "kalip_foto.png")

    assert out.classification == "yellow"
    assert out.dxf is not None
    assert out.metadata["scale_verified"] is True
    assert out.metadata["source"] == "photo"
    assert "KALIP" in out.dxf or "BEDEN_" in out.dxf


def test_vectorize_photo_invalid_bytes_is_red():
    out = vectorize_photo(b"not-an-image", "bad.png")
    assert out.classification == "red"
    assert out.dxf is None
