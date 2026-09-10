"""Shapely sarmalayıcı, mm cinsinden (mimari doküman §4.6, §6.1).

Kontur asla doğrudan kalıba çevrilmez; bu modül yalnızca zaten kurulmuş bir
`Piece`'in geometrik tutarlılığını (kapalılık, alan, kendiyle kesişme)
denetlemek ve DXF'e yazılabilecek düz bir nokta listesine çevirmek içindir.
"""
from __future__ import annotations

from shapely.geometry import Polygon

from ..models import Piece


class DisjointContourError(Exception):
    """Segment zinciri baştan sona kapalı bir döngü kurmuyor (kopuk parça listesi)."""


def ordered_boundary(piece: Piece) -> list[str]:
    """Segment zincirini `points` sırasına göre değil, `start->end` bağlantısına göre dolaşır.

    Segmentler §7.2 `closed_contour` kuralı gereği baştan sona zincirlenmiş olmalı;
    bu fonksiyon o zinciri tek bir nokta-id listesine çevirir (ilk noktaya dönmeden).
    """
    if not piece.segments:
        raise DisjointContourError(f"{piece.name}: hiç segment yok.")

    chain = [piece.segments[0].start]
    expected_start = piece.segments[0].start
    cursor = piece.segments[0].end
    chain.append(cursor)

    by_start = {seg.start: seg for seg in piece.segments}

    for _ in range(len(piece.segments) - 1):
        seg = by_start.get(cursor)
        if seg is None:
            raise DisjointContourError(
                f"{piece.name}: {cursor!r} noktasından devam eden segment yok — zincir kopuk."
            )
        cursor = seg.end
        chain.append(cursor)

    return chain


def closing_gap_mm(piece: Piece) -> float:
    """Son noktadan ilk noktaya olan mesafe (mm) — tamamen kapalıysa 0.0 olmalı."""
    chain = ordered_boundary(piece)
    first = piece.points[chain[0]]
    last = piece.points[chain[-1]]
    return ((first.x - last.x) ** 2 + (first.y - last.y) ** 2) ** 0.5


def polygon_from_piece(piece: Piece) -> Polygon:
    """Piece'in sınırını (segment zinciri boyunca) bir Shapely Polygon'a çevirir.

    Not: bloklar (Sprint 1-2) `arc`/`spline` kontrol noktası üretmiyor, tüm
    segmentler uç noktaları arasında düz hat — gerçek eğri interpolasyonu
    (kalıp geometrisinin kendisi değil, yalnızca doğrulama/görselleştirme
    yaklaşıklığı) ileri bir sprint kapsamındadır.
    """
    chain = ordered_boundary(piece)
    coords = [(piece.points[pid].x, piece.points[pid].y) for pid in chain]
    return Polygon(coords)


def area_mm2(polygon: Polygon) -> float:
    return float(polygon.area)


def is_simple(polygon: Polygon) -> bool:
    """Kendiyle kesişmiyor mu? Shapely'de kendiyle kesişen bir Polygon `is_valid=False` döner."""
    return bool(polygon.is_valid) and not polygon.is_empty


def edge_length(piece: Piece, point_a: str, point_b: str) -> float:
    """İki isimli nokta arası düz mesafe (mm) — parçalar-arası doğrulama kuralları için

    (ör. `shoulder_seam_match`, `armhole_sleeve_match`). Noktaların bir segmentle
    bağlı olması gerekmez, yalnızca `piece.points` içinde tanımlı olmaları yeter.
    """
    a = piece.points[point_a]
    b = piece.points[point_b]
    return ((a.x - b.x) ** 2 + (a.y - b.y) ** 2) ** 0.5
