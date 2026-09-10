import pytest

from pattern_core.engine.resolver import CircularDependencyError, resolve
from pattern_core.engine.spec import DerivedFormula, RulesSpec, Spec, ToleranceSpec, load_profile


def test_resolver_resolves_ts_default_8y():
    spec = load_profile("ts_default")
    measurements = resolve(spec, "8Y")

    assert measurements["chest_circ"] == 670
    assert measurements["chest_garment"] == 730  # 670 + ease.chest(60)
    assert measurements["front_width"] == pytest.approx(730 / 4)
    assert measurements["neck_width"] == pytest.approx(290 / 5)


def test_resolver_detects_circular_dependency():
    rules = RulesSpec(
        garment="tshirt",
        unit="mm",
        ease={},
        derived={
            "a": DerivedFormula(formula="b + 1", depends=["b"]),
            "b": DerivedFormula(formula="a + 1", depends=["a"]),
        },
    )
    sizes = load_profile("ts_default").sizes
    tolerances = ToleranceSpec(tolerances={})
    spec = Spec(profile="circular_test", sizes=sizes, rules=rules, tolerances=tolerances)

    with pytest.raises(CircularDependencyError):
        resolve(spec, "8Y")


def test_all_units_are_mm():
    with pytest.raises(ValueError):
        RulesSpec(garment="tshirt", unit="cm", ease={}, derived={})
