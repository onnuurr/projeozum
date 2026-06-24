"""Profil registry ve otomatik tespit.

detect_profile tüm profillerin match() skorunu hesaplar, en yükseği seçer.
Skor eşiğin altındaysa None (operatöre kırmızı/elle). Skorlar yakınsa
candidates doldurulur (operatör seçsin → sarı).
"""

from __future__ import annotations

from .base import ExtractionProfile
from .nipnaps import NipnapsProfile
from .ruslan import RuslanProfile

REGISTRY: list = [RuslanProfile(), NipnapsProfile()]

MIN_SCORE = 0.35
TIE_GAP = 0.15


def detect_profile(doc):
    scored = [(p, p.match(doc)) for p in REGISTRY]
    scored.sort(key=lambda t: t[1], reverse=True)
    best, best_score = scored[0]
    candidates = [
        {"name": p.name, "score": round(s, 3)}
        for p, s in scored if best_score - s <= TIE_GAP and s >= MIN_SCORE
    ]
    if best_score < MIN_SCORE:
        return None, round(best_score, 3), candidates
    return best, round(best_score, 3), candidates
