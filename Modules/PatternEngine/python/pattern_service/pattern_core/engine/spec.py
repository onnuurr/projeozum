"""YAML → doğrulanmış nesne (mimari doküman §5). Alan bilgisi koddan ayrı yaşar (§P3)."""
from __future__ import annotations

import re
import shutil
from pathlib import Path
from typing import Any

import yaml
from pydantic import BaseModel, ConfigDict, Field, field_validator

PROFILES_DIR = Path(__file__).resolve().parent.parent / "profiles"

# Profil adı doğrudan dizin adına dönüşür (§5.2) — path traversal'a karşı sıkı bir
# slug deseni zorunlu (yalnızca küçük harf/rakam/_/-,  "." veya "/" YOK).
_PROFILE_NAME_RE = re.compile(r"^[a-z0-9][a-z0-9_-]{0,63}$")


class InvalidProfileNameError(Exception):
    """Profil adı güvenli dizin-adı deseniyle eşleşmiyor."""


class ProtectedProfileError(Exception):
    """`ts_default` gibi repo'ya gömülü referans profiller API ile değiştirilemez."""


def validate_profile_name(name: str) -> None:
    if not _PROFILE_NAME_RE.fullmatch(name):
        raise InvalidProfileNameError(
            f"Geçersiz profil adı: {name!r} — yalnızca küçük harf, rakam, '_' ve '-' "
            "(ilk karakter harf/rakam), en fazla 64 karakter."
        )


class Provenance(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source: str
    verified_by_modelist: bool = False
    verified_at: str | None = None
    note: str | None = None


class SizesSpec(BaseModel):
    """`sizes.yaml` — her beden için taban (body) ölçüler + kaynak/onay durumu."""

    model_config = ConfigDict(extra="forbid")

    profile: str
    unit: str
    measurement_type: str
    sizes: dict[str, dict[str, float]]
    provenance: Provenance

    @field_validator("unit")
    @classmethod
    def _unit_must_be_mm(cls, value: str) -> str:
        if value != "mm":
            raise ValueError(f"Desteklenmeyen birim: {value!r} — motor yalnızca mm kabul eder (§6.1).")
        return value

    @field_validator("measurement_type")
    @classmethod
    def _measurement_type_valid(cls, value: str) -> str:
        if value not in ("body", "garment"):
            raise ValueError(f"measurement_type 'body' veya 'garment' olmalı, {value!r} geçersiz.")
        return value


class DerivedFormula(BaseModel):
    model_config = ConfigDict(extra="forbid")

    formula: str
    depends: list[str]


class RulesSpec(BaseModel):
    """`rules.yaml` — türetme formülleri. Modelistin bildiği kalıp bilgisinin matematiksel spesifikasyonu."""

    model_config = ConfigDict(extra="forbid")

    garment: str
    unit: str
    ease: dict[str, float] = Field(default_factory=dict)
    derived: dict[str, DerivedFormula]

    @field_validator("unit")
    @classmethod
    def _unit_must_be_mm(cls, value: str) -> str:
        if value != "mm":
            raise ValueError(f"Desteklenmeyen birim: {value!r} — motor yalnızca mm kabul eder (§6.1).")
        return value


class ToleranceSpec(BaseModel):
    model_config = ConfigDict(extra="allow")

    tolerances: dict[str, float]


class Spec:
    """Bir profilin (ts_default gibi) yüklenmiş, doğrulanmış tüm katmanları."""

    def __init__(self, profile: str, sizes: SizesSpec, rules: RulesSpec, tolerances: ToleranceSpec) -> None:
        self.profile = profile
        self.sizes = sizes
        self.rules = rules
        self.tolerances = tolerances

    def tolerance(self, key: str, default: float = 0.0) -> float:
        return float(self.tolerances.tolerances.get(key, default))


def _load_yaml(path: Path) -> dict[str, Any]:
    if not path.is_file():
        raise FileNotFoundError(f"Profil dosyası bulunamadı: {path}")
    with path.open("r", encoding="utf-8") as fh:
        return yaml.safe_load(fh) or {}


def load_profile(name: str) -> Spec:
    """`profiles/<name>/{meta,sizes,rules,tolerances}.yaml` dosyalarını yükler ve doğrular."""
    validate_profile_name(name)
    base = PROFILES_DIR / name
    sizes = SizesSpec.model_validate(_load_yaml(base / "sizes.yaml"))
    rules = RulesSpec.model_validate(_load_yaml(base / "rules.yaml"))
    tolerances = ToleranceSpec.model_validate(_load_yaml(base / "tolerances.yaml"))
    return Spec(profile=name, sizes=sizes, rules=rules, tolerances=tolerances)


def list_profiles() -> list[dict[str, Any]]:
    """Var olan tüm profillerin özeti (§5.2 — çok profillilik, `api/main.py:/profiles`).

    Yüklenemeyen (bozuk) bir profil listeyi kırmaz — sessizce atlanır; ayrıntı
    `GET /profiles/{name}` ile istenince asıl hatayı verir.
    """
    if not PROFILES_DIR.is_dir():
        return []

    summaries: list[dict[str, Any]] = []
    for entry in sorted(PROFILES_DIR.iterdir()):
        if not entry.is_dir() or not (entry / "sizes.yaml").is_file():
            continue
        try:
            spec = load_profile(entry.name)
        except Exception:
            continue
        summaries.append({
            "name": entry.name,
            "garment": spec.rules.garment,
            "sizes": sorted(spec.sizes.sizes.keys()),
            "source": spec.sizes.provenance.source,
            "verified_by_modelist": spec.sizes.provenance.verified_by_modelist,
        })
    return summaries


def create_or_update_profile(
    name: str,
    sizes: dict[str, dict[str, float]],
    provenance: dict[str, Any],
    base_profile: str = "ts_default",
) -> Spec:
    """Yeni bir profil yazar ya da var olanı günceller (§5.2, §9 Sprint 4).

    `sizes` MEVCUT bedenlerin üzerine BİRLEŞTİRİLİR (merge), TAMAMEN
    DEĞİŞTİRİLMEZ: yalnızca gönderilen beden anahtarları eklenir/güncellenir,
    profildeki diğer bedenler korunur. Aksi halde "profile bir beden daha
    ekle" işlemi, önceden girilmiş tüm bedenleri sessizce silerdi — bu §P4'ün
    "sessizce veri kaybı olmaz" ilkesine aykırı olurdu.

    Yalnızca `sizes.yaml` bu API'den gelir — `rules.yaml`/`tolerances.yaml`
    `base_profile`den kopyalanır (bir firmanın farklı olan şeyi ölçü tablosudur,
    çizim formülü değil, bkz. §5.2). `ts_default` repo'daki referans örnektir,
    API üzerinden ASLA üzerine yazılamaz (`ProtectedProfileError`).
    """
    validate_profile_name(name)
    if name == "ts_default":
        raise ProtectedProfileError("'ts_default' referans profildir, API ile değiştirilemez.")

    target_dir = PROFILES_DIR / name
    existing_sizes: dict[str, dict[str, float]] = {}
    if (target_dir / "sizes.yaml").is_file():
        try:
            existing_sizes = dict(load_profile(name).sizes.sizes)
        except Exception:
            existing_sizes = {}

    merged_sizes = {**existing_sizes, **sizes}

    payload = {
        "profile": name,
        "unit": "mm",
        "measurement_type": "body",
        "sizes": merged_sizes,
        "provenance": provenance,
    }
    # Diske yazmadan ÖNCE doğrula — yarım/geçersiz bir profil asla diskte oluşmaz (§P4).
    SizesSpec.model_validate(payload)

    base_spec = load_profile(base_profile)  # base_profile yoksa burada FileNotFoundError

    target_dir.mkdir(parents=True, exist_ok=True)

    with (target_dir / "sizes.yaml").open("w", encoding="utf-8") as fh:
        yaml.safe_dump(payload, fh, allow_unicode=True, sort_keys=False)

    if not (target_dir / "rules.yaml").is_file():
        shutil.copy(PROFILES_DIR / base_profile / "rules.yaml", target_dir / "rules.yaml")
    if not (target_dir / "tolerances.yaml").is_file():
        shutil.copy(PROFILES_DIR / base_profile / "tolerances.yaml", target_dir / "tolerances.yaml")
    if not (target_dir / "meta.yaml").is_file():
        meta = {"profile": name, "label": name, "garments": [base_spec.rules.garment], "unit": "mm"}
        with (target_dir / "meta.yaml").open("w", encoding="utf-8") as fh:
            yaml.safe_dump(meta, fh, allow_unicode=True, sort_keys=False)

    return load_profile(name)
