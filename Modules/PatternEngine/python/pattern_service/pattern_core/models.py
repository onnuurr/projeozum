"""Çekirdek veri modelleri (mimari doküman §6). Saf Python, framework bağımsız.

Tek birim: mm, float (§6.1). Piksel ve cm motora hiç girmez.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Literal


@dataclass(frozen=True)
class DesignParams:
    """AI önerisi veya kullanıcı girdisi — Pattern Engine bunu tek girdi olarak görür (§P2, §6.3)."""

    neck_depth_front: float | None = None
    sleeve_length: float | None = None
    body_length: float | None = None
    hem_flare: float = 0.0
    fit: Literal["slim", "regular", "loose"] = "regular"

    # kaynak takibi — AI önerisi mi, kullanıcı girdisi mi
    source: dict[str, str] = field(default_factory=dict)
    confidence: dict[str, float] = field(default_factory=dict)


@dataclass(frozen=True)
class NamedPoint:
    id: str                  # "shoulder_tip", "armhole_notch", "hem_side"
    x: float                 # mm
    y: float                 # mm


@dataclass(frozen=True)
class Segment:
    start: str               # NamedPoint.id
    end: str
    kind: Literal["line", "arc", "spline"]
    control: tuple[tuple[float, float], ...] = ()
    role: Literal["cut", "seam", "internal", "fold"] = "cut"


@dataclass(frozen=True)
class Piece:
    name: str                # "front_bodice", "sleeve"
    points: dict[str, NamedPoint]
    segments: tuple[Segment, ...]
    grainline: tuple[str, str]
    mirrored: bool = False   # simetri zorunludur, opsiyonel değil
    seam_allowance: float = 10.0


@dataclass(frozen=True)
class Pattern:
    garment: str
    size: str
    pieces: tuple[Piece, ...]
    profile: str
    design_params: DesignParams


@dataclass(frozen=True)
class GradedPattern:
    """Gradasyon çıktısı (§4.5) — sabit katsayıyla ölçeklenmiş DEĞİL, her beden
    kendi taban ölçülerinden bağımsız olarak yeniden çözülmüş (`resolve`) bir
    `Pattern` listesidir. `patterns[0]` her zaman talep edilen taban bedendir.
    """

    garment: str
    profile: str
    design_params: DesignParams
    patterns: tuple[Pattern, ...]

    @property
    def sizes(self) -> tuple[str, ...]:
        return tuple(p.size for p in self.patterns)
