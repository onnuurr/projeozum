"""Fotoğraftan kalıp çıkarma — ArUco referans marker ile ölçek + düzlem düzeltmesi.

Taranmış (raster) PDF'ten farkı: telefon fotoğrafının ne bilinen bir DPI'ı
(mutlak ölçek) ne de dik açılı (fronto-parallel) bir çekimi vardır. İkisini de
TEK referans nesneyle çözüyoruz — bilinen gerçek boyutlu (mm) bir ArUco kare,
kalıp kağıdıyla AYNI düz yüzeyde (masada) durur. Marker'ın 4 köşesinin piksel
konumu + bilinen mm boyutu, o düzlemin homografisini verir; TÜM fotoğraf bu
homografiyle "üstten dik" görünüme warp'lanır — yalnızca marker değil, AYNI
düzlemdeki her şey (kalıp kağıdı da) düzeltilmiş olur. Sonuç: bilinen px/mm
oranlı, dik açılı bir görüntü — mevcut raster_vectorize.vectorize_image()
bunu doğrudan işleyebilir (bkz. vectorize_photo).

Kısıt: kalıp kağıdı marker'la AYNI düzlemde (masada düz) olmalı — kağıt
kıvrık/bükülmüşse (kumaş üstünde, rüzgârda vb.) bu düzeltme geçersizdir;
operatör kalıbı düz bir yüzeye sermelidir. Bu kısıt DXF üretilmez/needs_tracing
değil, açık bir hata mesajıyla ('red') bildirilir — sessizce yanlış ölçekli
DXF üretmek dikimde kalıcı hataya yol açar.
"""

from __future__ import annotations

import cv2
import numpy as np

DEFAULT_MARKER_MM = 40.0
TARGET_PX_PER_MM = 300.0 / 25.4  # ~300 DPI eşdeğeri warp çözünürlüğü
MARKER_MARGIN_PX = 40            # warp çıktısında marker çevresinde bırakılan pay
MAX_WARP_PX = 12000              # bozuk/uzak marker tespiti → aşırı büyük çıktıya üst sınır


def _aruco_detector():
    aruco = cv2.aruco
    dictionary = aruco.getPredefinedDictionary(aruco.DICT_4X4_50)
    if hasattr(aruco, "ArucoDetector"):
        return aruco.ArucoDetector(dictionary, aruco.DetectorParameters())
    return None  # opencv < 4.7 API'sine main.py'de değil burada düşülür


def detect_marker(rgb: np.ndarray) -> np.ndarray | None:
    """İlk bulunan ArUco marker'ın 4 köşesini (px, TL,TR,BR,BL sırasıyla) döner ya da None."""
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
    detector = _aruco_detector()
    if detector is not None:
        corners, ids, _ = detector.detectMarkers(gray)
    else:
        aruco = cv2.aruco
        dictionary = aruco.getPredefinedDictionary(aruco.DICT_4X4_50)
        corners, ids, _ = aruco.detectMarkers(gray, dictionary)
    if ids is None or len(corners) == 0:
        return None
    return corners[0].reshape(4, 2).astype(np.float32)


def dewarp_and_scale(rgb: np.ndarray, marker_mm: float = DEFAULT_MARKER_MM,
                     target_px_per_mm: float = TARGET_PX_PER_MM):
    """Marker'ın düzlemine göre TÜM fotoğrafı dik açıya warp'lar.

    Marker aynı düzlemdeki her noktayı (kalıp kağıdı dahil) doğru ölçekte
    hizalar — kamera açısı/mesafesi ne olursa olsun. Döner: (warped_rgb,
    px_per_mm) ya da marker bulunamazsa (None, None).
    """
    marker_corners = detect_marker(rgb)
    if marker_corners is None:
        return None, None

    s = marker_mm * target_px_per_mm
    dst_marker = np.array([[0, 0], [s, 0], [s, s], [0, s]], np.float32)
    h_mat, _ = cv2.findHomography(marker_corners, dst_marker)
    if h_mat is None:
        return None, None

    src_h, src_w = rgb.shape[:2]
    src_corners = np.array([[0, 0], [src_w, 0], [src_w, src_h], [0, src_h]], np.float32)
    warped_corners = cv2.perspectiveTransform(src_corners.reshape(-1, 1, 2), h_mat).reshape(-1, 2)

    min_xy = warped_corners.min(axis=0) - MARKER_MARGIN_PX
    max_xy = warped_corners.max(axis=0) + MARKER_MARGIN_PX
    shift = np.array([[1, 0, -min_xy[0]], [0, 1, -min_xy[1]], [0, 0, 1]], np.float32)
    h_final = shift @ h_mat

    out_w = int(round(max_xy[0] - min_xy[0]))
    out_h = int(round(max_xy[1] - min_xy[1]))
    if out_w <= 0 or out_h <= 0:
        return None, None
    out_w, out_h = min(out_w, MAX_WARP_PX), min(out_h, MAX_WARP_PX)

    warped = cv2.warpPerspective(rgb, h_final, (out_w, out_h), borderValue=(255, 255, 255))
    return warped, target_px_per_mm


def vectorize_photo(data: bytes, filename: str = "", marker_mm: float = DEFAULT_MARKER_MM):
    """Fotoğraf bytes → ConvertOutput. Marker'la ölçek+düzlem düzeltir, mevcut raster
    çekirdeğine (vectorize_image) yönlendirir."""
    from converter import ROLE_LAYER, ConvertOutput, _stem, build_dxf_from_polylines
    from raster_vectorize import vectorize_image

    name = _stem(filename)
    arr = np.frombuffer(data, np.uint8)
    bgr = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if bgr is None:
        return ConvertOutput("red", 0.0, None, {"name": name, "source": "photo"},
                             ["Görüntü açılamadı (desteklenmeyen/bozuk dosya)."])
    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

    warped, px_per_mm = dewarp_and_scale(rgb, marker_mm)
    if warped is None:
        return ConvertOutput(
            "red", 0.0, None, {"name": name, "source": "photo"},
            [f"Referans marker (ArUco, DICT_4X4_50, {marker_mm:.0f}mm) bulunamadı — "
             "kalıp kağıdının yanına marker'ı düz bir yüzeyde, net ve tam görünecek "
             "şekilde koyup yeniden çekin."],
        )

    dpi = round(px_per_mm * 25.4)
    polys, meta = vectorize_image(warped, dpi=dpi)
    if not polys:
        return ConvertOutput(
            "red", 10.0, None,
            {"name": name, "source": "photo", "scale_verified": True, "marker_mm": marker_mm},
            ["Referans marker bulundu (ölçek doğrulandı) ama kesim çizgisi ayırt "
             "edilemedi — ışık/kontrast yetersiz olabilir, daha net çekin."],
        )

    all_polylines = []
    for role, pls in polys.items():
        for pl in pls:
            all_polylines.append({"role": role, "points": pl, "closed": False})

    try:
        dxf = build_dxf_from_polylines(all_polylines)
    except ValueError as exc:
        return ConvertOutput("red", 0.0, None, {"name": name, "source": "photo"}, [str(exc)])

    roles = sorted({p["role"] for p in all_polylines})
    size_layers = sorted({ROLE_LAYER[r][0] for r in roles
                          if ROLE_LAYER.get(r, ("KALIP",))[0].startswith("BEDEN_")})
    result_meta = {
        "name": name,
        "source": "photo",
        "scheme": meta["scheme"],
        "size_layers": size_layers,
        "segment_count": len(all_polylines),
        "scale_verified": True,  # marker fiziksel mm'e sabitler → PDF'ten farklı olarak GÜVENİLİR
        "scale_deviation_mm": None,
        "marker_mm": marker_mm,
    }
    return ConvertOutput(
        "yellow", 55.0, dxf, result_meta,
        ["Fotoğraftan otomatik vektörleştirme (referans marker ile ölçekli) — "
         "parça/beden ayrımı ve kesin kenar netliği operatör tarafından doğrulanmalı."],
    )
