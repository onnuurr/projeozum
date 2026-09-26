"""Gradasyon motoru (mimari doküman §4.5, §9 Sprint 3).

Çocuk gradasyonu lineer değildir (baş/gövde oranı yaşla değişir, küçük bedenlerde
göğüs pensi yoktur, bel çizgisi belirsizdir, ara bedenler kırılma gösterir) —
bu yüzden sabit bir katsayıyla ("8Y'yi %X büyüt") YAPILMAZ. Her beden, profildeki
kendi taban ölçülerinden (`sizes.yaml`) bağımsız olarak `resolve()` ile yeniden
çözülür ve aynı `design_params` ile yeniden bloklanır — "ölçekle" değil
"kendi verisinden yeniden çiz".
"""
from __future__ import annotations

from typing import Callable

from .blocks.pantolon import build_pantolon_back, build_pantolon_front, build_pantolon_waistband
from .blocks.tshirt import build_tshirt_back, build_tshirt_front, build_tshirt_sleeve
from .resolver import resolve
from .spec import Spec
from ..models import DesignParams, GradedPattern, Pattern, Piece

# Giysi tipi -> o tipi oluşturan blok fonksiyonlarının sırası. Yeni bir giysi tipi
# eklenince (Sprint 5+ kapsamı) yalnızca burada yeni bir anahtar açılır.
_BUILDERS: dict[str, tuple[Callable[[dict[str, float], DesignParams], Piece], ...]] = {
    "tshirt": (build_tshirt_front, build_tshirt_back, build_tshirt_sleeve),
    "pantolon": (build_pantolon_front, build_pantolon_back, build_pantolon_waistband),
}


class UnsupportedGarmentForGradingError(Exception):
    """Bu giysi tipi için blok fonksiyonları tanımlı değil."""


def grade(pattern: Pattern, sizes: list[str], spec: Spec) -> GradedPattern:
    """`pattern.size` + `sizes`'daki her beden için bağımsız bir `Pattern` üretir.

    Boru hattı imzası (§6.4): `graded = grade(pattern, ["4Y", "12Y"], spec)`.
    Taban beden (`pattern.size`) her zaman `patterns[0]`'dadır; `sizes` listesinde
    tekrar geçse bile ikinci kez üretilmez.
    """
    builders = _BUILDERS.get(pattern.garment)
    if builders is None:
        raise UnsupportedGarmentForGradingError(
            f"Gradasyon yalnızca {list(_BUILDERS)} için tanımlı, {pattern.garment!r} değil."
        )

    ordered_sizes = [pattern.size] + [s for s in sizes if s != pattern.size]

    graded_patterns: list[Pattern] = []
    for size in ordered_sizes:
        measurements = resolve(spec, size)
        pieces = tuple(builder(measurements, pattern.design_params) for builder in builders)
        graded_patterns.append(Pattern(
            garment=pattern.garment,
            size=size,
            pieces=pieces,
            profile=pattern.profile,
            design_params=pattern.design_params,
        ))

    return GradedPattern(
        garment=pattern.garment,
        profile=pattern.profile,
        design_params=pattern.design_params,
        patterns=tuple(graded_patterns),
    )
