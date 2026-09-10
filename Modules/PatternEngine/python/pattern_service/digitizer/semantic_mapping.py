"""Piksel oranı → güven skorlu `DesignParams` önerisi (mimari doküman §4.1).

**§P1'e mutlak uyum:** piksel hiçbir yerde doğrudan mm'e çevrilmez. Her mm
önerisi, boyutsuz bir oranın GERÇEK beden-tablosu ölçüsüyle (resolve() çıktısı)
çarpılmasıyla üretilir — görsel yalnızca BİÇİM taşır, ölçek her zaman beden
tablosundan gelir.

Çıktı ham sayı değildir — her alan `source='ai'` + bir güven skoru taşır
(§6.3 `DesignParams.source`/`confidence`, tam bunun için tasarlanmıştı).
Kullanıcı onaylamadan/düzeltmeden `PATTERN ENGINE`e geçmez (bkz. Laravel
tarafındaki öneri-önizleme akışı) — Digitizer asla otomatik üretim tetiklemez.
"""
from __future__ import annotations

from pattern_core.models import DesignParams

# Gözlemsel makul aralıklar (çocuk tişört oranları, yer tutucu) — bu aralık
# dışına çıkan bir oran "muhtemelen yanlış okundu" sayılır, güven düşürülür.
# Modelist onaylı gerçek dağılım geldiğinde bu sabitler buradan güncellenir
# (kod değil, yalnızca bu sabitler — kalan mantık değişmez).
_NECK_DEPTH_RATIO_RANGE = (0.05, 0.25)
_HEM_FLARE_RATIO_RANGE = (-0.05, 0.35)
_ASPECT_RATIO_RANGE = (0.9, 1.6)

_HIGH_CONFIDENCE = 0.8
_LOW_CONFIDENCE = 0.4
_REVIEW_THRESHOLD = 0.6


def _confidence(value: float, bounds: tuple[float, float]) -> float:
    low, high = bounds
    return _HIGH_CONFIDENCE if low <= value <= high else _LOW_CONFIDENCE


def to_design_params_suggestion(ratios: dict, measurements: dict) -> tuple[DesignParams, list[str]]:
    """`ratios` (analyze_tshirt_front_silhouette çıktısı) + `measurements`
    (resolve() çıktısı, mm) → (DesignParams önerisi, uyarı listesi).
    """
    body_length_ref = measurements["body_length"]
    front_width_ref = measurements["front_width"]

    neck_conf = _confidence(ratios["neck_depth_ratio"], _NECK_DEPTH_RATIO_RANGE)
    hem_conf = _confidence(ratios["hem_flare_ratio"], _HEM_FLARE_RATIO_RANGE)
    body_conf = _confidence(ratios["aspect_ratio"], _ASPECT_RATIO_RANGE)

    neck_depth_mm = round(ratios["neck_depth_ratio"] * body_length_ref, 1)
    hem_flare_mm = round(ratios["hem_flare_ratio"] * front_width_ref, 1)
    body_length_mm = round(ratios["aspect_ratio"] * front_width_ref, 1)

    design_params = DesignParams(
        neck_depth_front=neck_depth_mm,
        body_length=body_length_mm,
        hem_flare=hem_flare_mm,
        source={"neck_depth_front": "ai", "body_length": "ai", "hem_flare": "ai"},
        confidence={"neck_depth_front": neck_conf, "body_length": body_conf, "hem_flare": hem_conf},
    )

    warnings: list[str] = []
    if neck_conf < _REVIEW_THRESHOLD:
        warnings.append("Yaka derinliği önerisi düşük güvenli — görseldeki yaka çentiği belirsiz olabilir.")
    if hem_conf < _REVIEW_THRESHOLD:
        warnings.append("Etek genişlemesi önerisi düşük güvenli.")
    if body_conf < _REVIEW_THRESHOLD:
        warnings.append("Beden boyu önerisi düşük güvenli — en/boy oranı beklenenin dışında.")

    return design_params, warnings
