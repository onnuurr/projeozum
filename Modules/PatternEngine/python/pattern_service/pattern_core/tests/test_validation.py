import dataclasses

import pytest

from pattern_core.engine.spec import load_profile
from pattern_core.models import DesignParams
from pattern_core.pipeline import generate


def _generate_8y():
    return generate("tshirt", "8Y", "ts_default", DesignParams())


def test_closed_contour_and_positive_area_and_no_self_intersection():
    _, report = _generate_8y()

    by_id = {f.rule_id: f for f in report.findings}
    assert by_id["closed_contour"].passed
    assert by_id["positive_area"].passed
    assert by_id["no_self_intersection"].passed


def test_shoulder_seams_match():
    """§7.2: Ön omuz dikişi = arka omuz dikişi. Basit simetrik blokta (bkz.
    blocks/tshirt.py docstring) her zaman geçmesi beklenir."""
    _, report = _generate_8y()
    by_id = {f.rule_id: f for f in report.findings}

    assert by_id["shoulder_seam_match"].passed
    assert by_id["side_seam_match"].passed
    assert by_id["hem_match"].passed


def test_armhole_length_matches_sleeve_cap():
    """§7.2: Kol oyuntusu çevresi = kol kavisi uzunluğu (+ kabarma payı, ±8mm)."""
    _, report = _generate_8y()
    by_id = {f.rule_id: f for f in report.findings}

    finding = by_id["armhole_sleeve_match"]
    assert finding.passed, finding.message
    assert finding.tolerance == pytest.approx(8.0)


def test_all_sprint2_rules_pass_for_8y_placeholder_data():
    """Sprint 2'nin tüm kural seti (biye hariç, bkz. rules.py docstring) 8Y için geçmeli."""
    _, report = _generate_8y()
    assert report.passed, [f.message for f in report.failures]


def test_pattern_is_immutable():
    pattern, _ = _generate_8y()
    with pytest.raises(dataclasses.FrozenInstanceError):
        pattern.size = "12Y"  # type: ignore[misc]

    piece = pattern.pieces[0]
    with pytest.raises(dataclasses.FrozenInstanceError):
        piece.name = "other"  # type: ignore[misc]


def test_symmetry_flag_and_half_pattern_bounds():
    """Ön/arka merkez kat çizgisinde yarım kalıp (x>=0); kol tam parça, merkeze göre simetrik."""
    pattern, _ = _generate_8y()
    pieces = {p.name: p for p in pattern.pieces}

    assert pieces["front_bodice"].mirrored is True
    assert all(point.x >= 0 for point in pieces["front_bodice"].points.values())
    assert pieces["back_bodice"].mirrored is True
    assert all(point.x >= 0 for point in pieces["back_bodice"].points.values())

    sleeve = pieces["sleeve"]
    assert sleeve.mirrored is False
    xs = [p.x for p in sleeve.points.values()]
    assert min(xs) == pytest.approx(-max(xs))


def test_unit_is_mm_end_to_end():
    spec = load_profile("ts_default")
    assert spec.sizes.unit == "mm"
    assert spec.rules.unit == "mm"


@pytest.mark.skip(reason="Modelist onaylı referans kalıp bekleniyor (bkz. mimari doküman §8.1).")
def test_reference_pattern_8y():
    ...


@pytest.mark.skip(
    reason=(
        "neckline_band_match henüz uygulanmadı — bir biye/şerit parçası gerektirir, "
        "Sprint 2 kapsamı yalnızca arka+kol parçası (bkz. rules.py modül docstring'i)."
    )
)
def test_neckline_band_match():
    ...
