"""Bağımlılık grafiği + formül çözümleyici (mimari doküman §5.4).

`eval` kullanılmaz — `asteval` ile yalnızca aritmetik operatörler ve `depends`
alanından kurulan tanımlı isimler üzerinden hesap yapılır.
"""
from __future__ import annotations

import re

from asteval import Interpreter

from .spec import Spec

_EASE_TOKEN = re.compile(r"\bease\.(\w+)\b")


class CircularDependencyError(Exception):
    """`rules.yaml`'daki `derived` formülleri arasında döngüsel bağımlılık bulundu."""

    def __init__(self, cycle: list[str]) -> None:
        self.cycle = cycle
        super().__init__(f"Döngüsel bağımlılık tespit edildi: {' -> '.join(cycle)}")


class UnknownSizeError(Exception):
    """İstenen beden anahtarı bu profilde tanımlı değil."""


def _topological_order(derived: dict[str, "object"]) -> list[str]:
    """Kahn algoritmasıyla `depends` alanından topolojik sıra kurar; döngüyü hemen bulur."""
    in_degree: dict[str, int] = {name: 0 for name in derived}
    edges: dict[str, list[str]] = {name: [] for name in derived}

    for name, formula in derived.items():
        for dep in formula.depends:
            if dep in derived:
                edges[dep].append(name)
                in_degree[name] += 1

    ready = [name for name, deg in in_degree.items() if deg == 0]
    order: list[str] = []

    while ready:
        node = ready.pop(0)
        order.append(node)
        for dependent in edges[node]:
            in_degree[dependent] -= 1
            if in_degree[dependent] == 0:
                ready.append(dependent)

    if len(order) != len(derived):
        remaining = [name for name in derived if name not in order]
        raise CircularDependencyError(remaining)

    return order


def resolve(spec: Spec, size: str) -> dict[str, float]:
    """`Measurements = base(size) + derived(rules)` — sonuç düz `dict[str, float]` (mm).

    Boru hattı imzası (§6.4): `measure = resolve(spec, size="8Y")`.
    """
    if size not in spec.sizes.sizes:
        available = ", ".join(sorted(spec.sizes.sizes))
        raise UnknownSizeError(f"Beden {size!r} profil {spec.profile!r} içinde tanımlı değil. Mevcut: {available}")

    values: dict[str, float] = dict(spec.sizes.sizes[size])

    aeval = Interpreter(minimal=True, use_numpy=False)
    for name, value in values.items():
        aeval.symtable[name] = value
    for ease_name, ease_value in spec.rules.ease.items():
        aeval.symtable[f"ease__{ease_name}"] = ease_value

    order = _topological_order(spec.rules.derived)

    for name in order:
        formula = spec.rules.derived[name]
        expression = _EASE_TOKEN.sub(r"ease__\1", formula.formula)
        result = aeval(expression)
        if aeval.error:
            messages = "; ".join(err.get_error()[1] for err in aeval.error)
            aeval.error = []
            raise ValueError(f"Formül çözülemedi ({name} = {formula.formula!r}): {messages}")
        values[name] = float(result)
        aeval.symtable[name] = values[name]

    return values
