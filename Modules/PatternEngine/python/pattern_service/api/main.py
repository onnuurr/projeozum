"""FastAPI sarmalayıcı (mimari doküman §5.1 `api/`).

`pattern_core` hiçbir web framework'üne bağımlı değildir (§P2/§5.1); bu dosya
yalnızca HTTP sınırını kurar. Çalıştırma: `uvicorn api.main:app` (cwd=pattern_service,
bkz. `../..`'deki supervisor conf'u — Atelier'in `pdf_dxf_service`'i ile aynı desen).
"""
from __future__ import annotations

import base64
import logging

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field, ValidationError

from digitizer.semantic_mapping import to_design_params_suggestion
from digitizer.tshirt_front import DigitizerError, analyze_tshirt_front_silhouette
from pattern_core.engine.export.dxf import export_dxf_bytes, export_graded_dxf_bytes
from pattern_core.engine.resolver import CircularDependencyError, UnknownSizeError, resolve
from pattern_core.engine.spec import (
    InvalidProfileNameError,
    ProtectedProfileError,
    create_or_update_profile,
    list_profiles,
    load_profile,
)
from pattern_core.models import DesignParams, Pattern
from pattern_core.pipeline import UnsupportedGarmentError, generate, generate_graded
from pattern_core.engine.validation.report import ValidationReport

# Digitizer (§4.1) yalnızca bu giysi tiplerinde kural seti olgunlaştı (Sprint 5 kapsamı).
_DIGITIZER_SUPPORTED_GARMENTS = ("tshirt",)

logger = logging.getLogger("pattern_engine")

app = FastAPI(title="Çocuk Giyim Kalıp Motoru", version="0.1.0")


class DesignParamsIn(BaseModel):
    neck_depth_front: float | None = None
    sleeve_length: float | None = None
    body_length: float | None = None
    hem_flare: float = 0.0
    fit: str = "regular"
    source: dict[str, str] = Field(default_factory=dict)
    confidence: dict[str, float] = Field(default_factory=dict)

    def to_design_params(self) -> DesignParams:
        return DesignParams(
            neck_depth_front=self.neck_depth_front,
            sleeve_length=self.sleeve_length,
            body_length=self.body_length,
            hem_flare=self.hem_flare,
            fit=self.fit,  # type: ignore[arg-type]
            source=self.source,
            confidence=self.confidence,
        )


class GenerateRequest(BaseModel):
    garment: str
    size: str
    profile: str = "ts_default"
    design_params: DesignParamsIn = Field(default_factory=DesignParamsIn)


class GradeRequest(BaseModel):
    garment: str
    base_size: str
    target_sizes: list[str]
    profile: str = "ts_default"
    design_params: DesignParamsIn = Field(default_factory=DesignParamsIn)


class ProvenanceIn(BaseModel):
    source: str
    verified_by_modelist: bool = False
    verified_at: str | None = None
    note: str | None = None


class SaveProfileRequest(BaseModel):
    sizes: dict[str, dict[str, float]]
    provenance: ProvenanceIn
    base_profile: str = "ts_default"


def _pattern_to_json(pattern: Pattern) -> dict:
    return {
        "garment": pattern.garment,
        "size": pattern.size,
        "profile": pattern.profile,
        "pieces": [
            {
                "name": piece.name,
                "mirrored": piece.mirrored,
                "seam_allowance": piece.seam_allowance,
                "grainline": list(piece.grainline),
                "points": {pid: {"x": p.x, "y": p.y} for pid, p in piece.points.items()},
                "segments": [
                    {"start": s.start, "end": s.end, "kind": s.kind, "role": s.role}
                    for s in piece.segments
                ],
            }
            for piece in pattern.pieces
        ],
    }


def _modelist_warnings(profile: str) -> list[str]:
    spec = load_profile(profile)
    if spec.sizes.provenance.verified_by_modelist:
        return []
    return [
        "Ölçüler modelist onaylı değil (provenance.verified_by_modelist=false) — "
        "üretim vaadi verilmeden önce modelist onayı gerekir."
    ]


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "garments_supported": ["tshirt"]}


@app.post("/generate")
def generate_pattern(payload: GenerateRequest) -> dict:
    try:
        pattern, report = generate(
            payload.garment, payload.size, payload.profile, payload.design_params.to_design_params()
        )
    except UnsupportedGarmentError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except UnknownSizeError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except InvalidProfileNameError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=f"Profil bulunamadı: {e}") from e
    except CircularDependencyError as e:
        raise HTTPException(status_code=500, detail=str(e)) from e

    return {
        "pattern": _pattern_to_json(pattern),
        "dxf_base64": base64.b64encode(export_dxf_bytes(pattern)).decode("ascii"),
        "validation": report.as_dicts(),
        "all_passed": report.passed,
        "warnings": _modelist_warnings(payload.profile),
    }


@app.post("/grade")
def grade_pattern(payload: GradeRequest) -> dict:
    """§4.5 — taban beden + hedef bedenlerin her biri bağımsız çözülür (sabit
    katsayıyla ÖLÇEKLENMEZ) ve her biri ayrı ayrı doğrulanır; tek bir DXF'te
    her beden kendi satırında yer alır (§4.6, `export_graded_dxf_bytes`).
    """
    try:
        graded, reports = generate_graded(
            payload.garment,
            payload.base_size,
            payload.target_sizes,
            payload.profile,
            payload.design_params.to_design_params(),
        )
    except UnsupportedGarmentError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except UnknownSizeError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except InvalidProfileNameError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=f"Profil bulunamadı: {e}") from e
    except CircularDependencyError as e:
        raise HTTPException(status_code=500, detail=str(e)) from e

    reports_by_size: dict[str, ValidationReport] = reports

    return {
        "garment": graded.garment,
        "profile": graded.profile,
        "sizes": list(graded.sizes),
        "patterns": {pattern.size: _pattern_to_json(pattern) for pattern in graded.patterns},
        "validation": {size: report.as_dicts() for size, report in reports_by_size.items()},
        "all_passed_by_size": {size: report.passed for size, report in reports_by_size.items()},
        "dxf_base64": base64.b64encode(export_graded_dxf_bytes(graded)).decode("ascii"),
        "warnings": _modelist_warnings(payload.profile),
    }


@app.get("/profiles")
def list_profiles_endpoint() -> dict:
    """§5.2, §9 Sprint 4 — çok profillilik: var olan tüm profillerin özeti."""
    return {"profiles": list_profiles()}


@app.get("/profiles/{name}")
def get_profile_endpoint(name: str) -> dict:
    try:
        spec = load_profile(name)
    except InvalidProfileNameError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=f"Profil bulunamadı: {e}") from e

    return {
        "name": name,
        "garment": spec.rules.garment,
        "unit": spec.sizes.unit,
        "measurement_type": spec.sizes.measurement_type,
        "sizes": spec.sizes.sizes,
        "provenance": spec.sizes.provenance.model_dump(),
    }


@app.put("/profiles/{name}")
def save_profile_endpoint(name: str, payload: SaveProfileRequest) -> dict:
    """Yeni bir profil yaratır ya da var olanı günceller (upsert) — §5.2, Sprint 4.

    Yalnızca `sizes.yaml` bu uçtan gelir; `rules.yaml`/`tolerances.yaml`
    `base_profile`den kopyalanır (bkz. `create_or_update_profile` docstring'i).
    """
    try:
        spec = create_or_update_profile(
            name,
            payload.sizes,
            payload.provenance.model_dump(),
            base_profile=payload.base_profile,
        )
    except ProtectedProfileError as e:
        raise HTTPException(status_code=403, detail=str(e)) from e
    except InvalidProfileNameError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except ValidationError as e:
        raise HTTPException(status_code=422, detail=e.errors()) from e
    except FileNotFoundError as e:
        raise HTTPException(status_code=400, detail=f"base_profile bulunamadı: {e}") from e
    except PermissionError as e:
        logger.error("Profil dizinine yazma izni yok: %s", e)
        raise HTTPException(status_code=500, detail="Profil diske yazılamadı (izin hatası).") from e

    return {
        "name": name,
        "garment": spec.rules.garment,
        "sizes": spec.sizes.sizes,
        "provenance": spec.sizes.provenance.model_dump(),
    }


@app.post("/digitize")
async def digitize_endpoint(
    garment: str = Form(...),
    size: str = Form(...),
    profile: str = Form("ts_default"),
    image: UploadFile = File(...),
) -> dict:
    """Technical flat → güven skorlu `DesignParams` önerisi (§4.1, Sprint 5).

    AI omurga değil sağlayıcıdır (§P2) — bu uç asla otomatik kalıp üretmez,
    yalnızca `POST /generate`'e girdi olacak bir ÖNERİ döner. Kullanıcı
    onaylamadan/düzeltmeden kullanılmamalıdır (frontend sorumluluğu).
    """
    if garment not in _DIGITIZER_SUPPORTED_GARMENTS:
        raise HTTPException(
            status_code=400,
            detail=f"Digitizer yalnızca {_DIGITIZER_SUPPORTED_GARMENTS!r} destekler, {garment!r} değil.",
        )

    try:
        spec = load_profile(profile)
    except InvalidProfileNameError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=f"Profil bulunamadı: {e}") from e

    try:
        measurements = resolve(spec, size)
    except UnknownSizeError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    except CircularDependencyError as e:
        raise HTTPException(status_code=500, detail=str(e)) from e

    image_bytes = await image.read()
    try:
        ratios = analyze_tshirt_front_silhouette(image_bytes)
    except DigitizerError as e:
        raise HTTPException(status_code=422, detail=str(e)) from e

    design_params, warnings = to_design_params_suggestion(ratios, measurements)

    return {
        "design_params": {
            "neck_depth_front": design_params.neck_depth_front,
            "sleeve_length": design_params.sleeve_length,
            "body_length": design_params.body_length,
            "hem_flare": design_params.hem_flare,
            "fit": design_params.fit,
            "source": design_params.source,
            "confidence": design_params.confidence,
        },
        "silhouette": ratios,
        "warnings": warnings,
    }
