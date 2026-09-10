# Kalıp Motoru — Python servisi

Çocuk giyim kalıp motoru (mimari doküman: bkz. proje geçmişi). `pattern_core/`
saf Python, framework bağımsız (hiçbir web framework'üne ya da OpenCV'ye
bağımlı değil). `api/` bunu FastAPI ile sarar.

Sprint kapsamı: **yalnızca tişört, yalnızca 8Y beden, yalnızca ön parça**
(`front_bodice`). Gradasyon, çok parça, AI/Digitizer sonraki sprint'ler.

## Kurulum

```bash
cd Modules/PatternEngine/python/pattern_service
python3 -m venv .venv
.venv/bin/pip install -r requirements-dev.txt
```

## Çalıştırma

```bash
.venv/bin/uvicorn api.main:app --host 127.0.0.1 --port 8201
```

(Atelier'in `pdf_dxf_service`'i 8200'de çalıştığı için bu servis 8201 kullanır —
bkz. `/etc/supervisor/conf.d/patternengine-service.conf`.)

## Test

```bash
.venv/bin/pytest
```

## Uçlar

- `GET /health` — servis durumu.
- `POST /generate` — `{garment, size, profile, design_params}` → `{pattern, dxf_base64, validation, all_passed, warnings}`.

## Veri sorumluluğu

`pattern_core/profiles/ts_default/sizes.yaml` içindeki ölçüler **yer
tutucudur** (`provenance.verified_by_modelist: false`). Modelist onayı
gelmeden bu profille üretim vaadi verilmez (bkz. Laravel tarafındaki
`PatternEngineJobs.vue` uyarı banner'ı).
