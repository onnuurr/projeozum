"""Doğrulama çıktısı (mimari doküman §7.1, §P4). `False` dönmek yasak.

Her başarısızlık ölçülen değer, beklenen değer ve farkla birlikte döner.
"""
from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class Finding:
    rule_id: str
    passed: bool
    measured: float
    expected: float
    delta: float
    tolerance: float
    message: str             # Türkçe, kullanıcıya gösterilebilir
    suggestion: str | None   # hangi parametre nasıl değişmeli


@dataclass(frozen=True)
class ValidationReport:
    findings: tuple[Finding, ...]

    @property
    def passed(self) -> bool:
        return all(f.passed for f in self.findings)

    @property
    def failures(self) -> tuple[Finding, ...]:
        return tuple(f for f in self.findings if not f.passed)

    def as_dicts(self) -> list[dict]:
        return [
            {
                "rule_id": f.rule_id,
                "passed": f.passed,
                "measured": f.measured,
                "expected": f.expected,
                "delta": f.delta,
                "tolerance": f.tolerance,
                "message": f.message,
                "suggestion": f.suggestion,
            }
            for f in self.findings
        ]
