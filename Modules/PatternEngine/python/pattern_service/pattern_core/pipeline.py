"""Boru hattı imzası (mimari doküman §6.4): spec → resolve → build → validate → grade.

`export_dxf()` FastAPI katmanında ayrıca çağrılır (§4.6) — bu modül yalnızca
"ölçü + tasarım parametresi -> doğrulanmış Pattern(ler)" adımını sarar.
"""
from __future__ import annotations

from .engine.blocks.tshirt import build_tshirt_back, build_tshirt_front, build_tshirt_sleeve
from .engine.grading import grade
from .engine.resolver import resolve
from .engine.spec import load_profile
from .engine.validation.report import ValidationReport
from .engine.validation.rules import validate
from .models import DesignParams, GradedPattern, Pattern

SUPPORTED_GARMENTS: tuple[str, ...] = ("tshirt",)


class UnsupportedGarmentError(Exception):
    """İstenen giysi tipi bu sprint'te henüz desteklenmiyor."""


def _build_pattern(garment: str, size: str, profile: str, design_params: DesignParams) -> Pattern:
    if garment not in SUPPORTED_GARMENTS:
        raise UnsupportedGarmentError(
            f"Desteklenmeyen giysi tipi: {garment!r}. Şu an yalnızca {SUPPORTED_GARMENTS!r} destekleniyor "
            "(bkz. mimari doküman §9 — 'bu bitmeden ikinci giysi tipine geçilmez')."
        )

    spec = load_profile(profile)
    measurements = resolve(spec, size)

    pieces = (
        build_tshirt_front(measurements, design_params),
        build_tshirt_back(measurements, design_params),
        build_tshirt_sleeve(measurements, design_params),
    )

    return Pattern(garment=garment, size=size, pieces=pieces, profile=profile, design_params=design_params)


def generate(garment: str, size: str, profile: str, design_params: DesignParams) -> tuple[Pattern, ValidationReport]:
    spec = load_profile(profile)
    pattern = _build_pattern(garment, size, profile, design_params)
    report = validate(pattern, spec)
    return pattern, report


def generate_graded(
    garment: str,
    base_size: str,
    target_sizes: list[str],
    profile: str,
    design_params: DesignParams,
) -> tuple[GradedPattern, dict[str, ValidationReport]]:
    """§4.5 — taban beden + hedef bedenlerin her biri bağımsız çözülür, HER BİRİ
    ayrı ayrı doğrulanır (biri geçse diğeri geçmeyebilir — ara bedenler kırılma
    gösterebilir, bu yüzden tek bir toplu `all_passed` yeterli değildir).
    """
    spec = load_profile(profile)
    base_pattern = _build_pattern(garment, base_size, profile, design_params)
    graded = grade(base_pattern, target_sizes, spec)

    reports = {pattern.size: validate(pattern, spec) for pattern in graded.patterns}
    return graded, reports
