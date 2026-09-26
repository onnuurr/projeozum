"""Pantolon blokları — DOĞRULANMAMIŞ, ilk deneme (bkz. `pants_default/rules.yaml` başlığı).

Kullanıcının paylaştığı bir "kutu tekniği" ev dikişi tarifinden (tek beden: 2Y)
geriye doğru çıkarılan formüllerle çizilir. `tshirt.py`'deki gibi eğriler
(ağ kavisi, kalça eğrisi) düz hatlarla basitleştirilmiştir — gerçek kavis
DXF/validasyon için henüz gerekli değil (bkz. `geometry.py`: "gerçek eğri
interpolasyonu ileri bir sprint kapsamındadır").

Koordinat sistemi: her parçanın kendi "ütü çizgisi" x=0 merkezdir (tıpkı
tişörtün her parçasının kendi x=0 merkezi olması gibi), y aşağı doğru artar
(0 = bel hattı, +body_length = paça ucu).
"""
from __future__ import annotations

from ...models import DesignParams, NamedPoint, Piece, Segment


def build_pantolon_front(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """Ön paça — tam parça (katsız): iç paça (ağ) solda, dış paça (yan dikiş) sağda."""
    hip_half = measurements["hip_box_width"] / 2
    hip_h = measurements["hip_box_height"]
    crotch_ext = measurements["front_crotch_ext"]
    knee_half = measurements["front_knee_half"]
    knee_depth = measurements["knee_depth"]
    hem_half = measurements["front_hem_half"]
    side_in = measurements["side_waist_in"]

    body_length = (
        design_params.body_length
        if design_params.body_length is not None
        else measurements["body_length"]
    )
    # hem_flare: paça ucunu yanlara doğru genişletir (mm) — biçim parametresi (§P1).
    hem_half = hem_half + design_params.hem_flare / 2

    points = {
        "waist_front":  NamedPoint("waist_front", -hip_half, 0.0),
        "crotch_point": NamedPoint("crotch_point", -(hip_half + crotch_ext), hip_h),
        "inseam_knee":  NamedPoint("inseam_knee", -knee_half, knee_depth),
        "inseam_hem":   NamedPoint("inseam_hem", -hem_half, body_length),
        "outseam_hem":  NamedPoint("outseam_hem", hem_half, body_length),
        "outseam_knee": NamedPoint("outseam_knee", knee_half, knee_depth),
        "hip_side":     NamedPoint("hip_side", hip_half, hip_h),
        "waist_side":   NamedPoint("waist_side", hip_half - side_in, 0.0),
        # Sınır segmentinin parçası değil — yalnızca düz-boy (grainline) referansı.
        "crease_hem":   NamedPoint("crease_hem", 0.0, body_length),
    }

    segments = (
        Segment("waist_front", "crotch_point", kind="line", role="seam"),
        Segment("crotch_point", "inseam_knee", kind="line", role="seam"),
        Segment("inseam_knee", "inseam_hem", kind="line", role="seam"),
        Segment("inseam_hem", "outseam_hem", kind="line", role="cut"),
        Segment("outseam_hem", "outseam_knee", kind="line", role="seam"),
        Segment("outseam_knee", "hip_side", kind="line", role="seam"),
        Segment("hip_side", "waist_side", kind="line", role="seam"),
        Segment("waist_side", "waist_front", kind="line", role="cut"),
    )

    return Piece(
        name="front_leg",
        points=points,
        segments=segments,
        grainline=("waist_front", "crease_hem"),
        mirrored=False,
        seam_allowance=10.0,
    )


def build_pantolon_back(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """Arka paça — arka ütü çizgisi kutunun ortasında değil, sol kenardan `back_crease_offset` uzakta."""
    hip_w = measurements["hip_box_width"]
    hip_h = measurements["hip_box_height"]
    crease_off = measurements["back_crease_offset"]
    crotch_ext = measurements["back_crotch_ext"]
    knee_half = measurements["back_knee_half"]
    knee_depth = measurements["knee_depth"]
    hem_half = measurements["back_hem_half"]
    center_raise = measurements["back_center_raise"]
    waist_half = measurements["back_waist_half"]

    body_length = (
        design_params.body_length
        if design_params.body_length is not None
        else measurements["body_length"]
    )
    hem_half = hem_half + design_params.hem_flare / 2

    box_left = -crease_off
    box_right = hip_w - crease_off

    points = {
        "waist_center": NamedPoint("waist_center", box_left, -center_raise),
        "crotch_point": NamedPoint("crotch_point", box_left + crotch_ext, hip_h),
        "inseam_knee":  NamedPoint("inseam_knee", -knee_half, knee_depth),
        "inseam_hem":   NamedPoint("inseam_hem", -hem_half, body_length),
        "outseam_hem":  NamedPoint("outseam_hem", hem_half, body_length),
        "outseam_knee": NamedPoint("outseam_knee", knee_half, knee_depth),
        "hip_side":     NamedPoint("hip_side", box_right, hip_h),
        "waist_side":   NamedPoint("waist_side", box_left + waist_half, 0.0),
        "crease_hem":   NamedPoint("crease_hem", 0.0, body_length),
    }

    segments = (
        Segment("waist_center", "crotch_point", kind="line", role="seam"),
        Segment("crotch_point", "inseam_knee", kind="line", role="seam"),
        Segment("inseam_knee", "inseam_hem", kind="line", role="seam"),
        Segment("inseam_hem", "outseam_hem", kind="line", role="cut"),
        Segment("outseam_hem", "outseam_knee", kind="line", role="seam"),
        Segment("outseam_knee", "hip_side", kind="line", role="seam"),
        Segment("hip_side", "waist_side", kind="line", role="seam"),
        Segment("waist_side", "waist_center", kind="line", role="cut"),
    )

    return Piece(
        name="back_leg",
        points=points,
        segments=segments,
        grainline=("waist_center", "crease_hem"),
        mirrored=False,
        seam_allowance=10.0,
    )


def build_pantolon_waistband(measurements: dict[str, float], design_params: DesignParams) -> Piece:
    """Kemer — tek parça dikdörtgen (tarifte 6x61cm — bel_circ + pay uzunluğunda)."""
    length = measurements["waistband_length"]
    height = measurements["waistband_height"]

    points = {
        "top_left":     NamedPoint("top_left", 0.0, 0.0),
        "top_right":    NamedPoint("top_right", length, 0.0),
        "bottom_right": NamedPoint("bottom_right", length, height),
        "bottom_left":  NamedPoint("bottom_left", 0.0, height),
    }

    segments = (
        Segment("top_left", "top_right", kind="line", role="cut"),
        Segment("top_right", "bottom_right", kind="line", role="cut"),
        Segment("bottom_right", "bottom_left", kind="line", role="cut"),
        Segment("bottom_left", "top_left", kind="line", role="cut"),
    )

    return Piece(
        name="waistband",
        points=points,
        segments=segments,
        grainline=("top_left", "top_right"),
        mirrored=False,
        seam_allowance=10.0,
    )
