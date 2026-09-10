import pytest

from pattern_core.engine.grading import UnsupportedGarmentForGradingError, grade
from pattern_core.engine.resolver import resolve
from pattern_core.engine.spec import load_profile
from pattern_core.models import DesignParams, Pattern
from pattern_core.pipeline import generate_graded


def test_grade_produces_one_pattern_per_requested_size():
    graded, reports = generate_graded("tshirt", "8Y", ["4Y", "12Y"], "ts_default", DesignParams())

    assert graded.sizes == ("8Y", "4Y", "12Y")
    assert set(reports.keys()) == {"8Y", "4Y", "12Y"}


def test_grade_deduplicates_base_size():
    graded, _ = generate_graded("tshirt", "8Y", ["8Y", "4Y"], "ts_default", DesignParams())

    assert graded.sizes == ("8Y", "4Y")


def test_grade_each_size_geometry_is_structurally_sound():
    """§4.5: sabit katsayı yok — her beden kendi verisinden bağımsız çizilir.
    Tek-parça geometrik tutarlılık ve simetrik-blok eşleşmeleri (omuz/yan/etek)
    HER bedende geçmeli — bunlar formülün kendisiyle (front=back) garanti edilir."""
    _, reports = generate_graded("tshirt", "8Y", ["4Y", "12Y"], "ts_default", DesignParams())

    structural_rules = {"closed_contour", "positive_area", "no_self_intersection",
                         "shoulder_seam_match", "side_seam_match", "hem_match"}

    for size, report in reports.items():
        by_id = {f.rule_id: f for f in report.findings}
        for rule_id in structural_rules:
            assert by_id[rule_id].passed, f"{size}/{rule_id}: {by_id[rule_id].message}"


def test_grade_reveals_armhole_sleeve_ease_does_not_scale_linearly():
    """§4.5 risk kaydı: "Çocuk gradasyon kuralları yanlış | Ara bedenlerle doğrulama,
    modelist onayı". `ease.cap` sabiti yalnızca 8Y için kalibre edildi (Sprint 2);
    12Y'de kol kavisi-oyuntu farkı toleransın dışına taşıyor — bu YER TUTUCU
    formüllerin (kol kavisi yüksekliği/genişliği ile kol oyuntusu arasındaki
    ilişki) beden büyüdükçe sabit kalmadığının somut kanıtıdır. Modelist onaylı
    kol formülü gelene kadar beklenen, bilinen bir sınırlamadır — sessizce
    gizlenmez (§P4), burada açıkça test edilir."""
    _, reports = generate_graded("tshirt", "8Y", ["4Y", "12Y"], "ts_default", DesignParams())

    armhole_4y = next(f for f in reports["4Y"].findings if f.rule_id == "armhole_sleeve_match")
    armhole_12y = next(f for f in reports["12Y"].findings if f.rule_id == "armhole_sleeve_match")

    assert armhole_4y.passed
    assert not armhole_12y.passed
    assert armhole_12y.delta > armhole_12y.tolerance


def test_grade_is_not_a_linear_scale():
    """§4.5 risk kaydı: gradasyon lineer değildir — ara/komşu bedenler arası farkın
    sabit olmadığını, ölçü tablosundaki gerçek (yer tutucu) büyüme eğrisinden
    doğrudan doğrulayarak kanıtlar (bkz. sizes.yaml chest_circ: 580/670/780)."""
    spec = load_profile("ts_default")

    front_width_4y = resolve(spec, "4Y")["front_width"]
    front_width_8y = resolve(spec, "8Y")["front_width"]
    front_width_12y = resolve(spec, "12Y")["front_width"]

    delta_4_to_8 = front_width_8y - front_width_4y
    delta_8_to_12 = front_width_12y - front_width_8y

    assert delta_4_to_8 != pytest.approx(delta_8_to_12)


def test_grade_unsupported_garment_raises():
    spec = load_profile("ts_default")
    fake_pattern = Pattern(
        garment="pantolon", size="8Y", pieces=(), profile="ts_default", design_params=DesignParams()
    )

    with pytest.raises(UnsupportedGarmentForGradingError):
        grade(fake_pattern, ["4Y"], spec)
