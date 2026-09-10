"""Technical flat → geometrik primitive (mimari doküman §4.1, Sprint 5).

PNG → grayscale → threshold → contour (RETR_CCOMP) → basit kural tabanlı
semantic mapping. `pattern_core` bu pakete hiç bağımlı değildir (§11 —
"pattern-core OpenCV'ye bağımlı değildir"); yalnızca `digitizer/` OpenCV kullanır.

Bu, doküman §4.1'in kendi uyarısına göre boru hattının EN KIRILGAN halkasıdır.
Burada yalnızca "tshirt" için, tek bir temiz teknik çizim/silüet varsayımıyla
kural seti yazıldı — gerçek dünya çizim çeşitliliği için kural seti giysi
tipi başına olgunlaştırılmalıdır (§4.1: "Kural seti giysi tipi başına
yazılır. Tek tipte olgunlaştırılıp genişletilir.").
"""
from __future__ import annotations

import cv2
import numpy as np


class DigitizerError(Exception):
    """Görsel işlenemedi (bozuk dosya, kontur bulunamadı, dejenere siluet vb.)."""


def analyze_tshirt_front_silhouette(image_bytes: bytes) -> dict:
    """Bir tişört ön teknik çizimden PİKSEL ORANLARI çıkarır — mm YOK (§P1).

    Dönen değerler tamamen boyutsuz oranlardır; mm'e çevirme yalnızca
    `semantic_mapping.to_design_params_suggestion()` içinde, GERÇEK beden
    ölçüleriyle çarpılarak yapılır.
    """
    array = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(array, cv2.IMREAD_GRAYSCALE)
    if image is None:
        raise DigitizerError("Görsel okunamadı — desteklenmeyen format veya bozuk dosya.")

    _, binary = cv2.threshold(image, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    contours, _hierarchy = cv2.findContours(binary, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        raise DigitizerError("Siluet konturu bulunamadı — görsel net bir çizgi çizimi olmalı.")

    outer = max(contours, key=cv2.contourArea)
    x, y, w, h = cv2.boundingRect(outer)
    if w < 10 or h < 10:
        raise DigitizerError("Siluet dejenere (çok küçük/sıfır genişlik-yükseklik).")

    points = outer.reshape(-1, 2).astype(np.float64)

    # Yaka/omuz bandı: siluetin üst %25'i. Merkez bandı (±%20 genişlik) yaka
    # çentiğinin, kenar bandı omuz köşelerinin arandığı bölge.
    top_band = points[:, 1] <= y + 0.25 * h
    center_x = x + w / 2.0
    center_band = np.abs(points[:, 0] - center_x) <= 0.20 * w

    neckline_candidates = points[top_band & center_band]
    shoulder_candidates = points[top_band & ~center_band]

    if len(neckline_candidates) == 0 or len(shoulder_candidates) == 0:
        raise DigitizerError("Yaka/omuz bölgesi ayırt edilemedi — siluet çok basit veya gürültülü.")

    shoulder_y = float(np.min(shoulder_candidates[:, 1]))
    neckline_y = float(np.max(neckline_candidates[:, 1]))
    neck_depth_px = max(0.0, neckline_y - shoulder_y)

    # Göğüs bandı (orta %40-60 yükseklik) vs etek bandı (alt %15) genişlik karşılaştırması.
    chest_band = (points[:, 1] >= y + 0.40 * h) & (points[:, 1] <= y + 0.60 * h)
    hem_band = points[:, 1] >= y + 0.85 * h

    chest_points = points[chest_band]
    hem_points = points[hem_band]

    chest_width_px = float(np.ptp(chest_points[:, 0])) if len(chest_points) else float(w)
    hem_width_px = float(np.ptp(hem_points[:, 0])) if len(hem_points) else float(w)

    return {
        "width_px": float(w),
        "height_px": float(h),
        "contour_points": int(len(outer)),
        "neck_depth_ratio": neck_depth_px / h,
        "hem_flare_ratio": (hem_width_px - chest_width_px) / chest_width_px if chest_width_px else 0.0,
        "aspect_ratio": h / w,
    }
