"""PDF→DXF kalıp sayısallaştırma mikroservisi (FastAPI).

Laravel bu servise PDF gönderir (multipart 'file'); servis DXF + sınıflandırma +
metadata döndürür. Ağır iş Laravel'i bloklamasın diye ayrı süreç (roadmap §4).

Çalıştırma:
    pip install -r requirements.txt
    uvicorn main:app --host 0.0.0.0 --port 8200

Laravel tarafı .env:
    ATELIER_CONVERSION_DRIVER=http
    ATELIER_CONVERSION_URL=http://127.0.0.1:8200
"""

from __future__ import annotations

import base64

import fitz  # PyMuPDF
from fastapi import FastAPI, File, Form, UploadFile
from fastapi.responses import JSONResponse, Response

from converter import build_dxf_from_polylines, convert_pdf, probe_pdf

app = FastAPI(title="Atelier PDF→DXF", version="0.1.0")


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/probe")
async def probe(file: UploadFile = File(...)) -> JSONResponse:
    """Hızlı vektör/raster ön-kontrolü (DXF üretmez). Yükleme anında çağrılır."""
    data = await file.read()
    if not data:
        return JSONResponse({"kind": "empty", "pages": 0})
    return JSONResponse(probe_pdf(data, file.filename or ""))


@app.post("/convert")
async def convert(file: UploadFile = File(...)) -> JSONResponse:
    data = await file.read()
    if not data:
        return JSONResponse(
            {"classification": "red", "confidence": 0, "dxf_base64": None,
             "metadata": {}, "errors": ["Boş dosya."]},
            status_code=200,
        )

    result = convert_pdf(data, file.filename or "")

    dxf_b64 = (
        base64.b64encode(result.dxf.encode("utf-8")).decode("ascii")
        if result.dxf else None
    )

    return JSONResponse({
        "classification": result.classification,
        "confidence": result.confidence,
        "dxf_base64": dxf_b64,
        "metadata": result.metadata,
        "errors": result.errors,
    })


@app.post("/render")
async def render(file: UploadFile = File(...),
                 page: int = Form(0), dpi: int = Form(200)) -> Response:
    """PDF sayfasını PNG'ye render eder (tuval backdrop). Header'da sayfa sayısı/boyut."""
    data = await file.read()
    if not data:
        return JSONResponse({"errors": ["Boş dosya."]}, status_code=422)
    doc = fitz.open(stream=data, filetype="pdf")
    if page < 0 or page >= doc.page_count:
        return JSONResponse(
            {"errors": [f"Sayfa yok: {page} (toplam {doc.page_count})."]},
            status_code=422,
        )
    dpi = max(72, min(300, dpi))
    pix = doc[page].get_pixmap(dpi=dpi)
    png = pix.tobytes("png")
    return Response(content=png, media_type="image/png", headers={
        "X-Page-Count": str(doc.page_count),
        "X-Width": str(pix.width),
        "X-Height": str(pix.height),
    })


@app.post("/build-dxf")
async def build_dxf(payload: dict) -> JSONResponse:
    """İnsan-destekli izleme: mm cinsinden poligonlar → DXF."""
    polylines = payload.get("polylines", [])
    if not isinstance(polylines, list) or not polylines:
        return JSONResponse({"errors": ["polylines boş."]}, status_code=422)
    try:
        dxf = build_dxf_from_polylines(polylines)
    except ValueError as exc:
        return JSONResponse({"errors": [str(exc)]}, status_code=422)
    return JSONResponse({"dxf": dxf})
