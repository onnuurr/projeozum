"""`digitizer/` paketi testleri — pattern_core/tests altında yaşıyor (tek pytest
kökü/venv) ama `digitizer` pattern_core'a bağımlı DEĞİL, tersi de değil; yalnızca
`semantic_mapping` girdi olarak `resolve()` çıktısını (düz dict) alır."""
from __future__ import annotations

import cv2
import numpy as np
import pytest

from digitizer.tshirt_front import DigitizerError, analyze_tshirt_front_silhouette
from digitizer.semantic_mapping import to_design_params_suggestion
from pattern_core.engine.resolver import resolve
from pattern_core.engine.spec import load_profile


def _synthetic_tshirt_front_png() -> bytes:
    """Temiz, simetrik bir tişört ön silüeti çizer (V yaka, hafif etek genişlemesi)."""
    canvas = np.full((500, 400), 255, dtype=np.uint8)
    polygon = np.array([
        [170, 60], [200, 110], [230, 60],   # yaka çentiği (V)
        [300, 60],                            # sağ omuz
        [320, 150],                           # sağ kolçak
        [340, 450],                           # sağ etek
        [60, 450],                            # sol etek
        [80, 150],                            # sol kolçak
        [100, 60],                            # sol omuz
    ], dtype=np.int32)
    cv2.fillPoly(canvas, [polygon], color=0)
    ok, buf = cv2.imencode(".png", canvas)
    assert ok
    return buf.tobytes()


def _blank_png() -> bytes:
    canvas = np.full((200, 200), 255, dtype=np.uint8)
    ok, buf = cv2.imencode(".png", canvas)
    assert ok
    return buf.tobytes()


def test_analyze_extracts_plausible_ratios_from_clean_silhouette():
    ratios = analyze_tshirt_front_silhouette(_synthetic_tshirt_front_png())

    assert ratios["width_px"] == pytest.approx(280, abs=2)
    assert ratios["height_px"] == pytest.approx(390, abs=2)
    assert 0.08 < ratios["neck_depth_ratio"] < 0.18
    assert -0.05 < ratios["hem_flare_ratio"] < 0.35
    assert 1.0 < ratios["aspect_ratio"] < 1.6


def test_analyze_raises_on_blank_image():
    with pytest.raises(DigitizerError):
        analyze_tshirt_front_silhouette(_blank_png())


def test_analyze_raises_on_garbage_bytes():
    with pytest.raises(DigitizerError):
        analyze_tshirt_front_silhouette(b"not a real image")


def test_semantic_mapping_never_converts_pixels_directly_to_mm():
    """§P1 — mm değeri her zaman bir oran × GERÇEK ölçü tablosu değeridir.

    Aynı piksel siluetini iki farklı bedenin (4Y vs 12Y, çok farklı mm ölçekli)
    ölçüleriyle eşleyip önerilen mm değerlerinin de orantılı şekilde farklı
    çıktığını doğrular — sabit bir piksel→mm katsayısı olsaydı ikisi aynı çıkardı.
    """
    spec = load_profile("ts_default")
    ratios = analyze_tshirt_front_silhouette(_synthetic_tshirt_front_png())

    small = resolve(spec, "4Y")
    large = resolve(spec, "12Y")

    design_small, _ = to_design_params_suggestion(ratios, small)
    design_large, _ = to_design_params_suggestion(ratios, large)

    assert design_small.neck_depth_front != design_large.neck_depth_front
    assert design_small.body_length < design_large.body_length


def test_semantic_mapping_marks_source_as_ai_with_confidence():
    spec = load_profile("ts_default")
    measurements = resolve(spec, "8Y")
    ratios = analyze_tshirt_front_silhouette(_synthetic_tshirt_front_png())

    design_params, warnings = to_design_params_suggestion(ratios, measurements)

    assert design_params.source["neck_depth_front"] == "ai"
    assert 0.0 <= design_params.confidence["neck_depth_front"] <= 1.0
    # Temiz/simetrik test silüeti makul aralıkta olmalı → yüksek güven, uyarı yok.
    assert design_params.confidence["neck_depth_front"] == pytest.approx(0.8)
    assert warnings == []


def test_semantic_mapping_flags_low_confidence_for_implausible_ratio():
    spec = load_profile("ts_default")
    measurements = resolve(spec, "8Y")

    # Gerçekçi olmayan oranlar — hiçbir gerçek çizimden gelmemeli.
    implausible_ratios = {
        "width_px": 100.0, "height_px": 500.0, "contour_points": 10,
        "neck_depth_ratio": 0.9, "hem_flare_ratio": 5.0, "aspect_ratio": 5.0,
    }

    design_params, warnings = to_design_params_suggestion(implausible_ratios, measurements)

    assert design_params.confidence["neck_depth_front"] < 0.6
    assert len(warnings) == 3
