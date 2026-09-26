"""Başlangıç kural seti (mimari doküman §7.2).

Sprint 1 yalnızca `front_bodice` ürettiği için tek-parça geometrik tutarlılık
kurallarıyla (`closed_contour`, `positive_area`, `no_self_intersection`)
sınırlıydı. Sprint 2'de `back_bodice` + `sleeve` eklenince parçalar-arası
kurallar (`shoulder_seam_match`, `side_seam_match`, `armhole_sleeve_match`,
`hem_match`) da devreye girer.

`neckline_band_match` KASITLI OLARAK burada yok: doküman "yaka çevresi = yaka
biye uzunluğu × gerilim katsayısı" diyor, ama bu, ayrı bir biye/şerit parçasının
kendi kesim uzunluğunu gerektirir — Sprint 2 kapsamı yalnızca arka+kol parçası
istiyor, biye parçası yok. Biye parçası eklenmeden bu kuralı "yaklaşık" bir
sayıyla doldurmak (ör. ham `neck_circ`'e kıyasla) modelist bilgisi icat etmek
olurdu (§P3 ihlali) — bu yüzden bir biye parçası tasarlanana kadar ertelendi.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Callable, Literal

from ..geometry import area_mm2, closing_gap_mm, edge_length, is_simple, polygon_from_piece
from ..spec import Spec
from ...models import Pattern, Piece
from .report import Finding, ValidationReport


@dataclass(frozen=True)
class Rule:
    id: str
    description: str
    measure_fn: Callable[[Pattern], float]
    expected_fn: Callable[[Pattern, Spec], float]
    tolerance: float
    severity: Literal["error", "warning"]
    # "tolerance" -> |measured-expected| <= tolerance geçer (eşitlik kuralları, §7.2 tablosu)
    # "at_least"  -> measured >= expected geçer (eşiği geçme kuralları, ör. alan > 0)
    comparator: Literal["tolerance", "at_least"] = "tolerance"
    # Doluysa tolerans `Rule.tolerance` yerine `spec.tolerance(tolerance_key)`den okunur
    # (§P3 — profilin `tolerances.yaml`ı, tek tek Rule sabitleri değil).
    tolerance_key: str | None = None


def _piece(pattern: Pattern, name: str) -> Piece:
    for piece in pattern.pieces:
        if piece.name == name:
            return piece
    raise KeyError(f"Pattern'da {name!r} adlı parça yok (mevcut: {[p.name for p in pattern.pieces]}).")


def _edge(pattern: Pattern, piece_name: str, point_a: str, point_b: str) -> float:
    return edge_length(_piece(pattern, piece_name), point_a, point_b)


def _max_closing_gap(pattern: Pattern) -> float:
    return max((closing_gap_mm(piece) for piece in pattern.pieces), default=0.0)


def _min_area(pattern: Pattern) -> float:
    return min((area_mm2(polygon_from_piece(piece)) for piece in pattern.pieces), default=0.0)


def _self_intersection_flag(pattern: Pattern) -> float:
    """0 = hiçbir parça kendiyle kesişmiyor, 1 = en az biri kesişiyor."""
    return 1.0 if any(not is_simple(polygon_from_piece(piece)) for piece in pattern.pieces) else 0.0


def _armhole_perimeter(pattern: Pattern) -> float:
    front = _edge(pattern, "front_bodice", "shoulder_tip", "armhole_notch")
    back = _edge(pattern, "back_bodice", "shoulder_tip", "armhole_notch")
    return front + back


def _sleeve_cap_length(pattern: Pattern) -> float:
    sleeve = _piece(pattern, "sleeve")
    return (
        edge_length(sleeve, "cap_back_underarm", "cap_top")
        + edge_length(sleeve, "cap_top", "cap_front_underarm")
    )


CLOSED_CONTOUR = Rule(
    id="closed_contour",
    description="Tüm parçalar kapalı (segment zinciri başladığı noktaya geri döner)",
    measure_fn=_max_closing_gap,
    expected_fn=lambda pattern, spec: 0.0,
    tolerance=0.01,
    severity="error",
)

POSITIVE_AREA = Rule(
    id="positive_area",
    description="Alan > 0, dejenere parça yok",
    measure_fn=_min_area,
    expected_fn=lambda pattern, spec: 0.0,
    tolerance=0.0,
    severity="error",
    comparator="at_least",
)

NO_SELF_INTERSECTION = Rule(
    id="no_self_intersection",
    description="Poligon kendini kesmiyor",
    measure_fn=_self_intersection_flag,
    expected_fn=lambda pattern, spec: 0.0,
    tolerance=0.0,
    severity="error",
)

SHOULDER_SEAM_MATCH = Rule(
    id="shoulder_seam_match",
    description="Ön omuz dikişi = arka omuz dikişi",
    measure_fn=lambda pattern: _edge(pattern, "front_bodice", "shoulder_neck", "shoulder_tip"),
    expected_fn=lambda pattern, spec: _edge(pattern, "back_bodice", "shoulder_neck", "shoulder_tip"),
    tolerance=0.0,
    severity="error",
    tolerance_key="seam_match",
)

SIDE_SEAM_MATCH = Rule(
    id="side_seam_match",
    description="Ön yan dikiş = arka yan dikiş",
    measure_fn=lambda pattern: _edge(pattern, "front_bodice", "armhole_notch", "hem_side"),
    expected_fn=lambda pattern, spec: _edge(pattern, "back_bodice", "armhole_notch", "hem_side"),
    tolerance=0.0,
    severity="error",
    tolerance_key="seam_match",
)

HEM_MATCH = Rule(
    id="hem_match",
    description="Ön etek ucu = arka etek ucu",
    measure_fn=lambda pattern: _edge(pattern, "front_bodice", "hem_center", "hem_side"),
    expected_fn=lambda pattern, spec: _edge(pattern, "back_bodice", "hem_center", "hem_side"),
    tolerance=0.0,
    severity="error",
    tolerance_key="seam_match",
)

ARMHOLE_SLEEVE_MATCH = Rule(
    id="armhole_sleeve_match",
    description="Kol oyuntusu çevresi = kol kavisi uzunluğu (+ kabarma payı)",
    measure_fn=_sleeve_cap_length,
    expected_fn=lambda pattern, spec: _armhole_perimeter(pattern) + spec.rules.ease.get("cap", 0.0),
    tolerance=0.0,
    severity="error",
    tolerance_key="armhole_sleeve",
)

SPRINT_1_RULES: tuple[Rule, ...] = (CLOSED_CONTOUR, POSITIVE_AREA, NO_SELF_INTERSECTION)

SPRINT_2_RULES: tuple[Rule, ...] = SPRINT_1_RULES + (
    SHOULDER_SEAM_MATCH,
    SIDE_SEAM_MATCH,
    HEM_MATCH,
    ARMHOLE_SLEEVE_MATCH,
)

ALL_RULES: tuple[Rule, ...] = SPRINT_2_RULES


def _leg_inseam_length(piece_name: str) -> Callable[[Pattern], float]:
    """`crotch_point -> inseam_knee -> inseam_hem` zincirinin toplam uzunluğu (mm)."""
    return lambda pattern: (
        _edge(pattern, piece_name, "crotch_point", "inseam_knee")
        + _edge(pattern, piece_name, "inseam_knee", "inseam_hem")
    )


def _leg_side_length(piece_name: str, waist_point: str) -> Callable[[Pattern], float]:
    """`waist_point -> hip_side -> outseam_knee -> outseam_hem` zincirinin toplam uzunluğu (mm)."""
    return lambda pattern: (
        _edge(pattern, piece_name, waist_point, "hip_side")
        + _edge(pattern, piece_name, "hip_side", "outseam_knee")
        + _edge(pattern, piece_name, "outseam_knee", "outseam_hem")
    )


def _waistband_length(pattern: Pattern) -> float:
    return _edge(pattern, "waistband", "top_left", "top_right")


def _front_back_waist_sum(pattern: Pattern) -> float:
    """Her parça (ön/arka) bir bacak için kesilir, iki bacak için ×2 kesilir — kemer
    tüm beden çevresini sardığı için karşılaştırma da tam çevre üzerinden yapılır.
    """
    return 2.0 * (
        _edge(pattern, "front_leg", "waist_side", "waist_front")
        + _edge(pattern, "back_leg", "waist_side", "waist_center")
    )


PANTS_INSEAM_MATCH = Rule(
    id="pants_inseam_match",
    description="Ön iç paça uzunluğu = arka iç paça uzunluğu",
    measure_fn=_leg_inseam_length("front_leg"),
    expected_fn=lambda pattern, spec: _leg_inseam_length("back_leg")(pattern),
    tolerance=0.0,
    severity="error",
    tolerance_key="seam_match",
)

PANTS_SIDE_SEAM_MATCH = Rule(
    id="pants_side_seam_match",
    description="Ön dış paça/yan dikiş uzunluğu = arka dış paça/yan dikiş uzunluğu",
    measure_fn=_leg_side_length("front_leg", "waist_side"),
    expected_fn=lambda pattern, spec: _leg_side_length("back_leg", "waist_side")(pattern),
    tolerance=0.0,
    severity="error",
    tolerance_key="seam_match",
)

PANTS_WAISTBAND_MATCH = Rule(
    id="pants_waistband_match",
    description="Kemer uzunluğu ≈ ön bel + arka bel toplamı",
    measure_fn=_waistband_length,
    expected_fn=lambda pattern, spec: _front_back_waist_sum(pattern),
    tolerance=0.0,
    severity="warning",
    tolerance_key="waistband_match",
)

PANTOLON_RULES: tuple[Rule, ...] = SPRINT_1_RULES + (
    PANTS_INSEAM_MATCH,
    PANTS_SIDE_SEAM_MATCH,
    PANTS_WAISTBAND_MATCH,
)

# Giysi tipi -> doğrulama kural seti. Yeni bir giysi tipi eklenince (bkz.
# `engine/blocks/`, `engine/grading.py::_BUILDERS`) buraya da bir anahtar açılır.
RULES_BY_GARMENT: dict[str, tuple[Rule, ...]] = {
    "tshirt": SPRINT_2_RULES,
    "pantolon": PANTOLON_RULES,
}


def _run_rule(rule: Rule, pattern: Pattern, spec: Spec) -> Finding:
    measured = rule.measure_fn(pattern)
    expected = rule.expected_fn(pattern, spec)
    delta = measured - expected
    tolerance = spec.tolerance(rule.tolerance_key) if rule.tolerance_key else rule.tolerance

    if rule.comparator == "at_least":
        passed = measured >= expected
    else:
        passed = abs(delta) <= tolerance

    if rule.id == "positive_area":
        message = (
            f"{rule.description}: en küçük parça alanı {measured:.1f} mm² (>0 olmalı)."
            if passed
            else f"{rule.description}: dejenere parça — alan {measured:.1f} mm² (>0 olmalı)."
        )
        suggestion = None if passed else "Parçayı üreten blok fonksiyonunu kontrol edin (noktalar çakışıyor olabilir)."
    elif rule.id == "no_self_intersection":
        message = (
            "Poligon kendiyle kesişmiyor." if passed
            else "En az bir parça kendiyle kesişiyor — geometri geçersiz."
        )
        suggestion = None if passed else "Blok fonksiyonundaki nokta sırasını / segment zincirini kontrol edin."
    else:
        message = (
            f"{rule.description}: ölçülen {measured:.2f} mm, beklenen {expected:.2f} mm, "
            f"fark {delta:+.2f} mm (tolerans ±{tolerance} mm)."
        )
        suggestion = None if passed else "Segment zincirinin start/end noktalarının eşleştiğini doğrulayın."

    return Finding(
        rule_id=rule.id,
        passed=passed,
        measured=measured,
        expected=expected,
        delta=delta,
        tolerance=tolerance,
        message=message,
        suggestion=suggestion,
    )


def validate(pattern: Pattern, spec: Spec, rules: tuple[Rule, ...] = ALL_RULES) -> ValidationReport:
    """Boru hattı imzası (§6.4): `report = validate(pattern, spec)`.

    Sprint 1'de üretilen tek-parçalı `Pattern`lerde (yalnızca `front_bodice`)
    parçalar-arası kurallar `KeyError` fırlatır — çağıran taraf bu fonksiyona
    yalnızca en az `front_bodice`+`back_bodice`+`sleeve` üreten (Sprint 2+)
    bir `Pattern` vermelidir; `pipeline.generate()` bunu garanti eder.
    """
    findings = tuple(_run_rule(rule, pattern, spec) for rule in rules)
    return ValidationReport(findings=findings)
