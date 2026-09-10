"""Tişört blokları (Sprint 2 kapsamı — `front_bodice` + `back_bodice` + `sleeve`).

Temel bloklar parametrik olarak deforme edilir (§4.3); çıktı her zaman dikilebilir
yapıdadır çünkü bloktan türer. Gradasyon ve ek giysi tipleri Sprint 3+ kapsamındadır
(mimari doküman §9) — kasıtlı olarak burada yok.

Ön/arka: yarım parça (center kat çizgisinde `mirrored=True`) olarak çizilir: x=0
merkez kat çizgisi, y aşağı doğru artar (0 = omuz hattı, +body_length = etek).
Kol: tam parça (`mirrored=False`) — merkez üst nokta (kol tepe) x=0, iki yanına
simetrik açılır; kolun kendi kat çizgisi yoktur.

Ön ve arka panel, basit/simetrik bir çocuk tişört bloğu için KASITLI olarak aynı
`front_width`/`armhole_depth_calc`/`shoulder_slope`/`body_length` formüllerini
paylaşır (yalnızca yaka derinliği farklı) — bu yüzden `shoulder_seam_match`,
`side_seam_match`, `hem_match` doğrulamaları bu haliyle her zaman geçer. Bu bir
kusur değil: doğru çizilmiş simetrik bir temel blok için beklenen sonuç budur;
kural yine de gelecekte (ör. arkaya omuz payı eklenirse) bir sapmayı yakalar.
"""
from __future__ import annotations

from ...models import DesignParams, NamedPoint, Piece, Segment


def build_tshirt_front(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """`Measurements + DesignParams -> Piece` (§4.3 imzasının tek-parça karşılığı).

    `design_params`'taki `None` olmayan alanlar `rules.yaml` türetmesinin yerine geçer (§6.3).
    """
    front_width = measurements["front_width"]
    armhole_depth = measurements["armhole_depth_calc"]
    neck_width = measurements["neck_width"]
    shoulder_slope = measurements["shoulder_slope"]

    neck_depth_front = (
        design_params.neck_depth_front
        if design_params.neck_depth_front is not None
        else measurements["neck_depth_front"]
    )
    body_length = (
        design_params.body_length
        if design_params.body_length is not None
        else measurements["body_length"]
    )
    # hem_flare: etek ucunu yanlara doğru genişletir (mm) — biçim parametresi, ölçek değil (§P1).
    hem_x = front_width + design_params.hem_flare

    points = {
        "neck_center":   NamedPoint("neck_center", 0.0, neck_depth_front),
        "shoulder_neck": NamedPoint("shoulder_neck", neck_width, 0.0),
        "shoulder_tip":  NamedPoint("shoulder_tip", front_width, shoulder_slope),
        "armhole_notch": NamedPoint("armhole_notch", front_width, armhole_depth),
        "hem_side":      NamedPoint("hem_side", hem_x, body_length),
        "hem_center":    NamedPoint("hem_center", 0.0, body_length),
    }

    segments = (
        Segment("neck_center", "shoulder_neck", kind="line", role="cut"),
        Segment("shoulder_neck", "shoulder_tip", kind="line", role="seam"),
        Segment("shoulder_tip", "armhole_notch", kind="line", role="seam"),
        Segment("armhole_notch", "hem_side", kind="line", role="seam"),
        Segment("hem_side", "hem_center", kind="line", role="cut"),
        Segment("hem_center", "neck_center", kind="line", role="fold"),
    )

    return Piece(
        name="front_bodice",
        points=points,
        segments=segments,
        grainline=("neck_center", "hem_center"),
        mirrored=True,
        seam_allowance=10.0,
    )


def build_tshirt_back(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """Arka parça — ön ile aynı gövde formülleri, yalnızca yaka derinliği farklı (`neck_depth_back`).

    `DesignParams`'ın `neck_depth_front` alanı yalnızca öne uygulanır; arka yaka
    derinliği için ayrı bir tasarım parametresi doküman §6.3'te tanımlı değil,
    her zaman `rules.yaml` türetmesinden gelir.
    """
    front_width = measurements["front_width"]
    armhole_depth = measurements["armhole_depth_calc"]
    neck_width = measurements["neck_width"]
    shoulder_slope = measurements["shoulder_slope"]
    neck_depth_back = measurements["neck_depth_back"]

    body_length = (
        design_params.body_length
        if design_params.body_length is not None
        else measurements["body_length"]
    )
    hem_x = front_width + design_params.hem_flare

    points = {
        "neck_center":   NamedPoint("neck_center", 0.0, neck_depth_back),
        "shoulder_neck": NamedPoint("shoulder_neck", neck_width, 0.0),
        "shoulder_tip":  NamedPoint("shoulder_tip", front_width, shoulder_slope),
        "armhole_notch": NamedPoint("armhole_notch", front_width, armhole_depth),
        "hem_side":      NamedPoint("hem_side", hem_x, body_length),
        "hem_center":    NamedPoint("hem_center", 0.0, body_length),
    }

    segments = (
        Segment("neck_center", "shoulder_neck", kind="line", role="cut"),
        Segment("shoulder_neck", "shoulder_tip", kind="line", role="seam"),
        Segment("shoulder_tip", "armhole_notch", kind="line", role="seam"),
        Segment("armhole_notch", "hem_side", kind="line", role="seam"),
        Segment("hem_side", "hem_center", kind="line", role="cut"),
        Segment("hem_center", "neck_center", kind="line", role="fold"),
    )

    return Piece(
        name="back_bodice",
        points=points,
        segments=segments,
        grainline=("neck_center", "hem_center"),
        mirrored=True,
        seam_allowance=10.0,
    )


def build_tshirt_sleeve(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """Kol — tam parça (katsız), kol kalıbının basitleştirilmiş 5 noktalı hali.

    `cap_top` merkez üst nokta (omuz dikişine hizalı); `cap_front_underarm` ve
    `cap_back_underarm` kol kavisinin ön/arka kolçak noktaları; `hem_front`/`hem_back`
    kol ucu. Kısa kollu tişört için düz (daralmayan) kol varsayılır.
    """
    sleeve_cap_height = measurements["sleeve_cap_height"]
    sleeve_half_width = measurements["sleeve_width_calc"] / 2.0

    sleeve_length = (
        design_params.sleeve_length
        if design_params.sleeve_length is not None
        else measurements["arm_length"]
    )

    points = {
        "cap_top":            NamedPoint("cap_top", 0.0, 0.0),
        "cap_front_underarm": NamedPoint("cap_front_underarm", sleeve_half_width, sleeve_cap_height),
        "cap_back_underarm":  NamedPoint("cap_back_underarm", -sleeve_half_width, sleeve_cap_height),
        "hem_front":          NamedPoint("hem_front", sleeve_half_width, sleeve_length),
        "hem_back":           NamedPoint("hem_back", -sleeve_half_width, sleeve_length),
        # Sınır segmentlerinin parçası değil — yalnızca düz-boy (grainline) referansı,
        # kolun merkezinden dikey iner (cap_top ile aynı x=0).
        "hem_center":         NamedPoint("hem_center", 0.0, sleeve_length),
    }

    segments = (
        Segment("cap_back_underarm", "cap_top", kind="line", role="seam"),
        Segment("cap_top", "cap_front_underarm", kind="line", role="seam"),
        Segment("cap_front_underarm", "hem_front", kind="line", role="seam"),
        Segment("hem_front", "hem_back", kind="line", role="cut"),
        Segment("hem_back", "cap_back_underarm", kind="line", role="seam"),
    )

    return Piece(
        name="sleeve",
        points=points,
        segments=segments,
        grainline=("cap_top", "hem_center"),
        mirrored=False,
        seam_allowance=10.0,
    )
