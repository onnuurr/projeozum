# Raster Kalıp Sayısallaştırma (İnsan-Destekli İzleme) — Uygulama Planı

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Raster (taranmış) kalıp PDF'lerini, web tuvalinde insan-destekli izleme ile mm cinsinden DXF'e çevirip mevcut kalıp kütüphanesine kaydetmek.

**Architecture:** Yeni Vue tuval editörü (`RasterTracer.vue`) taranmış sayfayı arka plana koyar; kullanıcı ölçeği kalibre edip parçaları çizer. Python servisi sayfayı PNG'ye render eder ve izlenen mm-poligonlarını mevcut `_build_dxf` ile DXF'e yazar. Raster PDF'ler `importPdf`'te artık atlanmaz; `extraction_status=needs_tracing` taslağı olur. Kaydedilen DXF mevcut kütüphane akışına girer.

**Tech Stack:** Laravel 11 (Inertia + Vue 3), Python 3.14 FastAPI (PyMuPDF `fitz`, ezdxf), Pest/PHPUnit (sınıf-stili), pytest + FastAPI TestClient, Spatie permissions, Storage `public` diski.

## Global Constraints

- DB şema değişikliği YOK — `extraction_status` varchar kolonu; yeni değer migration gerektirmez (proje DB disiplini).
- Tüm yeni HTTP uçları `can:atelier.pattern.manage` izni arkasında.
- Tüm kullanıcıya görünen metinler Türkçe (mevcut Atelier deseni).
- Python servisi `ATELIER_CONVERSION_URL` (varsayılan `http://127.0.0.1:8200`); servissiz/CI test için `MockPdfDxfConverter` kullanılır.
- DXF birimi MM; DXF y-yukarı (görüntü y-aşağı → `y_mm = (image_height_px - y_px) / px_per_mm`).
- Laravel testleri `tests/Feature/Atelier/` ve `tests/Unit/Atelier/` altında, `Tests\TestCase` + `RefreshDatabase`, PHPUnit sınıf-stili.
- Mevcut desenleri izle: `PatternLibraryService` `deleteFile`/`syncParts`, `ConversionResult`, `HttpPdfDxfConverter` `Http::timeout` hata yönetimi.

---

### Task 1: Python `/build-dxf` — mm poligon → DXF

**Files:**
- Modify: `Modules/Atelier/python/pdf_dxf_service/converter.py` (ROLE_LAYER'a `grain`; yeni `build_dxf_from_polylines`)
- Modify: `Modules/Atelier/python/pdf_dxf_service/main.py` (yeni `/build-dxf` endpoint)
- Test: `Modules/Atelier/python/pdf_dxf_service/test_endpoints.py` (yeni)

**Interfaces:**
- Produces: `build_dxf_from_polylines(polylines: list[dict]) -> str` (her dict: `{"role": str, "points": [[x,y],...], "closed": bool}`); `POST /build-dxf` JSON `{"polylines":[...]}` → `{"dxf": str}` (geçersizse 422 `{"errors":[...]}`).

- [ ] **Step 1: Failing test yaz** — `Modules/Atelier/python/pdf_dxf_service/test_endpoints.py`

```python
from fastapi.testclient import TestClient
from main import app

client = TestClient(app)


def test_build_dxf_closed_square_has_four_segments():
    # 100x100 mm kapalı kesim konturu → DXF + 4 LINE + KALIP katmanı
    square = [[0, 0], [100, 0], [100, 100], [0, 100]]
    resp = client.post("/build-dxf", json={
        "polylines": [{"role": "cut", "points": square, "closed": True}]
    })
    assert resp.status_code == 200
    dxf = resp.json()["dxf"]
    assert "KALIP" in dxf
    assert dxf.count("\nLINE\n") == 4  # kapalı → 4 kenar


def test_build_dxf_rejects_degenerate():
    resp = client.post("/build-dxf", json={
        "polylines": [{"role": "cut", "points": [[0, 0]], "closed": True}]
    })
    assert resp.status_code == 422
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_endpoints.py -v`
Expected: FAIL (404 — `/build-dxf` yok)

- [ ] **Step 3: `converter.py`'ye ekle** — ROLE_LAYER'a grain rolü + yardımcı fonksiyon

`ROLE_LAYER` sözlüğüne (mevcut tanım, ~satır 33) yeni satır ekle:
```python
    "grain":   ("GRAINLINE", 8),
```
Dosyada `_build_dxf` tanımının HEMEN ARDINA ekle:
```python
def build_dxf_from_polylines(polylines) -> str:
    """mm cinsinden poligonları (izleme çıktısı) DXF'e çevirir. role bilinmiyorsa KALIP."""
    lines = []
    for pl in polylines:
        role = pl.get("role", "cut")
        pts = pl.get("points", [])
        closed = pl.get("closed", True)
        if len(pts) < 2:
            continue
        seq = list(pts)
        if closed and len(pts) >= 3:
            seq = seq + [pts[0]]
        for i in range(len(seq) - 1):
            x1, y1 = seq[i]
            x2, y2 = seq[i + 1]
            lines.append((role, round(float(x1), 3), round(float(y1), 3),
                          round(float(x2), 3), round(float(y2), 3)))
    if not lines:
        raise ValueError("Geçerli poligon yok (kenar üreten en az 2 nokta gerekli).")
    return _build_dxf(lines, [])
```
> Not: `cut` rolü ROLE_LAYER'da yok → `_layer_for` zaten `("KALIP", 7)`'ye düşer (istenen). `grain` ayrı katman alır.

- [ ] **Step 4: `main.py`'ye endpoint ekle**

Üst importları güncelle (mevcut `from fastapi import FastAPI, File, UploadFile` satırını değiştir):
```python
from fastapi import FastAPI, File, Form, UploadFile
from fastapi.responses import JSONResponse, Response
```
`from converter import convert_pdf, probe_pdf` satırını değiştir:
```python
from converter import build_dxf_from_polylines, convert_pdf, probe_pdf
```
Dosya sonuna ekle:
```python
@app.post("/build-dxf")
async def build_dxf(payload: dict) -> JSONResponse:
    polylines = payload.get("polylines", [])
    if not isinstance(polylines, list) or not polylines:
        return JSONResponse({"errors": ["polylines boş."]}, status_code=422)
    try:
        dxf = build_dxf_from_polylines(polylines)
    except ValueError as exc:
        return JSONResponse({"errors": [str(exc)]}, status_code=422)
    return JSONResponse({"dxf": dxf})
```

- [ ] **Step 5: Testi çalıştır, PASS gör**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_endpoints.py -v`
Expected: PASS (2 test)

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/converter.py Modules/Atelier/python/pdf_dxf_service/main.py Modules/Atelier/python/pdf_dxf_service/test_endpoints.py
git commit -m "feat(atelier): Python /build-dxf — mm poligon → DXF (raster izleme)"
```

---

### Task 2: Python `/render` — PDF sayfası → PNG

**Files:**
- Modify: `Modules/Atelier/python/pdf_dxf_service/main.py` (yeni `/render` endpoint)
- Test: `Modules/Atelier/python/pdf_dxf_service/test_endpoints.py` (mevcut, ekle)

**Interfaces:**
- Produces: `POST /render` multipart `file` + form `page` (int, vars. 0) + `dpi` (int, vars. 200) → `image/png` body, header `X-Page-Count`, `X-Width`, `X-Height`. Sayfa aralık dışıysa 422.

- [ ] **Step 1: Failing test ekle** — `test_endpoints.py` sonuna

```python
import fitz


def _sample_pdf_bytes(pages=2):
    doc = fitz.open()
    for _ in range(pages):
        doc.new_page(width=595, height=842)  # A4 pt
    return doc.tobytes()


def test_render_returns_png_with_page_count():
    pdf = _sample_pdf_bytes(pages=3)
    resp = client.post("/render", files={"file": ("p.pdf", pdf, "application/pdf")},
                       data={"page": 0, "dpi": 150})
    assert resp.status_code == 200
    assert resp.headers["content-type"] == "image/png"
    assert resp.headers["x-page-count"] == "3"
    assert resp.content[:8] == b"\x89PNG\r\n\x1a\n"


def test_render_out_of_range_page():
    pdf = _sample_pdf_bytes(pages=1)
    resp = client.post("/render", files={"file": ("p.pdf", pdf, "application/pdf")},
                       data={"page": 5})
    assert resp.status_code == 422
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_endpoints.py -k render -v`
Expected: FAIL (404)

- [ ] **Step 3: `main.py`'ye `/render` ekle**

```python
@app.post("/render")
async def render(file: UploadFile = File(...),
                 page: int = Form(0), dpi: int = Form(200)) -> Response:
    data = await file.read()
    if not data:
        return JSONResponse({"errors": ["Boş dosya."]}, status_code=422)
    doc = fitz.open(stream=data, filetype="pdf")
    if page < 0 or page >= doc.page_count:
        return JSONResponse({"errors": [f"Sayfa yok: {page} (toplam {doc.page_count})."]},
                            status_code=422)
    dpi = max(72, min(300, dpi))
    pix = doc[page].get_pixmap(dpi=dpi)
    png = pix.tobytes("png")
    return Response(content=png, media_type="image/png", headers={
        "X-Page-Count": str(doc.page_count),
        "X-Width": str(pix.width),
        "X-Height": str(pix.height),
    })
```
`import fitz` zaten converter üzerinden değil; `main.py` başına `import fitz` ekle (yoksa).

- [ ] **Step 4: Testi çalıştır, PASS gör**

Run: `cd Modules/Atelier/python/pdf_dxf_service && python -m pytest test_endpoints.py -v`
Expected: PASS (4 test)

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/python/pdf_dxf_service/main.py Modules/Atelier/python/pdf_dxf_service/test_endpoints.py
git commit -m "feat(atelier): Python /render — PDF sayfası → PNG (tuval backdrop)"
```

---

### Task 3: Raster PDF'i `needs_tracing` taslağına yönlendir

**Files:**
- Modify: `Modules/Atelier/Models/Pattern.php` (yeni sabit)
- Modify: `Modules/Atelier/Services/PatternLibraryService.php` (`markNeedsTracing`, `createRasterDraft`)
- Modify: `Modules/Atelier/Http/Controllers/PatternController.php:86-120` (`importPdf` raster yolu)
- Test: `tests/Feature/Atelier/PatternRasterImportTest.php` (yeni)

**Interfaces:**
- Produces: `Pattern::EXTRACTION_NEEDS_TRACING = 'needs_tracing'`; `PatternLibraryService::createRasterDraft(UploadedFile $pdf, ?int $createdBy = null): Pattern`; `PatternLibraryService::markNeedsTracing(Pattern $pattern): void`.

- [ ] **Step 1: Failing test yaz** — `tests/Feature/Atelier/PatternRasterImportTest.php`

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternRasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
    }

    public function test_raster_pdf_becomes_needs_tracing_draft(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('atelier.pattern.manage');

        $this->mock(PdfDxfConverterContract::class, function ($m) {
            $m->shouldReceive('probe')->andReturn(['kind' => 'raster']);
        });

        $this->actingAs($user)
            ->post('/atelier/patterns/import', [
                'files' => [UploadedFile::fake()->create('shik.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patterns', [
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
        ]);
    }
}
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `php artisan test --filter=PatternRasterImportTest`
Expected: FAIL (raster PDF atlanıyor; `patterns` boş)

- [ ] **Step 3: `Pattern.php`'ye sabit ekle**

`EXTRACTION_FAILED` satırının (satır 22) ardına:
```php
    public const EXTRACTION_NEEDS_TRACING = 'needs_tracing';
```

- [ ] **Step 4: `PatternLibraryService.php`'ye metodlar ekle**

`createPdfDraft` metodunun ardına:
```php
    /**
     * Raster (taranmış) PDF için 'sayısallaştırma bekliyor' taslağı oluşturur.
     * Otomatik çıkarım YOK — kullanıcı tuval editöründe elle izler.
     */
    public function createRasterDraft(UploadedFile $pdf, ?int $createdBy = null): Pattern
    {
        $path = $pdf->store(self::DIR, 'public');

        return Pattern::create([
            'name'              => $this->stem($pdf->getClientOriginalName()),
            'product_type'      => 'belirsiz',
            'status'            => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
            'pdf_path'          => $path,
            'created_by'        => $createdBy,
        ]);
    }
```
`markExtractionFailed` metodunun ardına:
```php
    public function markNeedsTracing(Pattern $pattern): void
    {
        $pattern->update([
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
            'extraction_error'  => null,
        ]);
    }
```

- [ ] **Step 5: `PatternController::importPdf`'i güncelle** (satır 95-107 döngüsü)

Mevcut döngüdeki raster atlama bloğunu şununla değiştir:
```php
        $imported = 0;
        $tracing  = 0;
        $skipped  = [];
        foreach ($request->file('files') as $pdf) {
            $probe = $this->converter->probe($pdf->getRealPath());
            $kind  = $probe['kind'] ?? null;

            // Raster/taranmış → otomatik çıkarım yerine sayısallaştırma taslağı.
            if ($kind === 'raster') {
                $this->library->createRasterDraft($pdf, $request->user()?->id);
                $tracing++;
                continue;
            }
            // Boş/geçersiz → gerçekten kullanılamaz, atla.
            if (in_array($kind, ['empty', 'invalid'], true)) {
                $skipped[] = $pdf->getClientOriginalName();
                continue;
            }

            $pattern = $this->library->createPdfDraft($pdf, $request->user()?->id);
            ExtractPatternFromPdfJob::dispatch($pattern->id);
            $imported++;
        }
```
Ve dönüş mesajlarını (satır 109-119) şununla değiştir:
```php
        $parts = [];
        if ($imported > 0) { $parts[] = "{$imported} PDF incelemeye alındı"; }
        if ($tracing > 0)  { $parts[] = "{$tracing} taranmış PDF sayısallaştırma için hazır"; }
        if (! empty($skipped)) { $parts[] = 'atlandı (boş/geçersiz): ' . implode(', ', $skipped); }

        if ($imported === 0 && $tracing === 0) {
            return back()->with('error', 'Hiçbir PDF işlenemedi. ' . implode('; ', $parts));
        }

        return redirect()->route('atelier.patterns.index')->with('success', implode('; ', $parts) . '.');
```

- [ ] **Step 6: Testi çalıştır, PASS gör**

Run: `php artisan test --filter=PatternRasterImportTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add Modules/Atelier/Models/Pattern.php Modules/Atelier/Services/PatternLibraryService.php Modules/Atelier/Http/Controllers/PatternController.php tests/Feature/Atelier/PatternRasterImportTest.php
git commit -m "feat(atelier): raster PDF importta needs_tracing taslağı olur"
```

---

### Task 4: `PatternLibraryService::applyTracedDxf`

**Files:**
- Modify: `Modules/Atelier/Services/PatternLibraryService.php` (yeni `applyTracedDxf`)
- Test: `tests/Unit/Atelier/ApplyTracedDxfTest.php` (yeni)

**Interfaces:**
- Consumes: `Pattern::EXTRACTION_DONE`, `deleteFile`, `syncParts` (mevcut).
- Produces: `applyTracedDxf(Pattern $pattern, string $dxf, array $meta, array $parts): Pattern` — `$meta` anahtarları `name`, `product_type`, `size_range`; `$parts` her biri `['part_name','quantity','size_range']`.

- [ ] **Step 1: Failing test yaz** — `tests/Unit/Atelier/ApplyTracedDxfTest.php`

```php
<?php

namespace Tests\Unit\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\PatternLibraryService;
use Tests\TestCase;

class ApplyTracedDxfTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_traced_dxf_stores_file_parts_and_marks_done(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('atelier/patterns/old.dxf', 'ESKI');

        $pattern = Pattern::create([
            'name' => 'Taslak', 'product_type' => 'belirsiz',
            'status' => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
            'pdf_path' => 'atelier/patterns/x.pdf',
            'dxf_path' => 'atelier/patterns/old.dxf',
        ]);

        $service = app(PatternLibraryService::class);
        $service->applyTracedDxf($pattern, "0\nSECTION\n0\nEOF\n", [
            'name' => 'Ceket', 'product_type' => 'ceket', 'size_range' => '38-44',
        ], [['part_name' => 'Ön', 'quantity' => 2, 'size_range' => '38']]);

        $fresh = $pattern->fresh('parts');
        $this->assertSame(Pattern::EXTRACTION_DONE, $fresh->extraction_status);
        $this->assertSame('ceket', $fresh->product_type);
        $this->assertTrue($fresh->scale_verified);
        $this->assertNotNull($fresh->dxf_path);
        Storage::disk('public')->assertExists($fresh->dxf_path);
        Storage::disk('public')->assertMissing('atelier/patterns/old.dxf');
        $this->assertSame(1, $fresh->parts->count());
        $this->assertSame('Ön', $fresh->parts->first()->part_name);
    }
}
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `php artisan test --filter=ApplyTracedDxfTest`
Expected: FAIL (`applyTracedDxf` yok)

- [ ] **Step 3: `applyTracedDxf`'i ekle** — `PatternLibraryService.php`, `applyExtraction` ardına

```php
    /**
     * İnsan-destekli izleme çıktısını taslağa uygular: DXF'i saklar, parçaları
     * yazar, ölçek doğrulanmış sayar, durumu 'done' yapar. (applyExtraction deseni.)
     *
     * @param  array<string,mixed>             $meta   name, product_type, size_range
     * @param  array<int,array<string,mixed>>  $parts
     */
    public function applyTracedDxf(Pattern $pattern, string $dxf, array $meta, array $parts): Pattern
    {
        return DB::transaction(function () use ($pattern, $dxf, $meta, $parts) {
            $this->deleteFile($pattern->dxf_path);
            $dxfPath = self::DIR . '/' . Str::uuid() . '.dxf';
            Storage::disk('public')->put($dxfPath, $dxf);

            $pattern->update([
                'name'              => $meta['name'] ?? $pattern->name,
                'product_type'      => $meta['product_type'] ?? $pattern->product_type,
                'size_range'        => $meta['size_range'] ?? $pattern->size_range,
                'dxf_path'          => $dxfPath,
                'scale_verified'    => true,
                'extraction_status' => Pattern::EXTRACTION_DONE,
                'extraction_error'  => null,
            ]);

            $this->syncParts($pattern, $parts);

            return $pattern->fresh('parts');
        });
    }
```

- [ ] **Step 4: Testi çalıştır, PASS gör**

Run: `php artisan test --filter=ApplyTracedDxfTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/PatternLibraryService.php tests/Unit/Atelier/ApplyTracedDxfTest.php
git commit -m "feat(atelier): applyTracedDxf — izleme çıktısını kütüphaneye yazar"
```

---

### Task 5: Converter sözleşmesine `renderPage` + `buildDxf`

**Files:**
- Modify: `Modules/Atelier/Services/Conversion/Contracts/PdfDxfConverterContract.php`
- Modify: `Modules/Atelier/Services/Conversion/Drivers/HttpPdfDxfConverter.php`
- Modify: `Modules/Atelier/Services/Conversion/Drivers/MockPdfDxfConverter.php`
- Test: `tests/Unit/Atelier/MockConverterTracingTest.php` (yeni)

**Interfaces:**
- Produces: `PdfDxfConverterContract::renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string` (PNG bytes); `PdfDxfConverterContract::buildDxf(array $polylines): string` (DXF text). `$polylines`: her biri `['role'=>str,'points'=>[[x_mm,y_mm],...],'closed'=>bool]`.

- [ ] **Step 1: Failing test yaz** — `tests/Unit/Atelier/MockConverterTracingTest.php`

```php
<?php

namespace Tests\Unit\Atelier;

use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Tests\TestCase;

class MockConverterTracingTest extends TestCase
{
    public function test_mock_render_and_build_dxf(): void
    {
        $mock = new MockPdfDxfConverter();

        $png = $mock->renderPage('/yok/x.pdf', 0, 200);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $dxf = $mock->buildDxf([
            ['role' => 'cut', 'points' => [[0, 0], [10, 0], [10, 10]], 'closed' => true],
        ]);
        $this->assertStringContainsString('EOF', $dxf);
    }
}
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `php artisan test --filter=MockConverterTracingTest`
Expected: FAIL (`renderPage`/`buildDxf` yok)

- [ ] **Step 3: Sözleşmeye metodları ekle** — `PdfDxfConverterContract.php` interface gövdesine

```php
    /** PDF sayfasını PNG'ye render eder (tuval backdrop). PNG bytes döner. */
    public function renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string;

    /**
     * mm cinsinden poligonları DXF'e çevirir.
     * @param array<int,array{role:string,points:array<int,array{0:float,1:float}>,closed:bool}> $polylines
     */
    public function buildDxf(array $polylines): string;
```

- [ ] **Step 4: `HttpPdfDxfConverter`'a uygulamaları ekle** (sınıf gövdesine)

```php
    public function renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string
    {
        if (! is_file($pdfAbsolutePath)) {
            throw new RuntimeException("Render edilecek PDF bulunamadı: {$pdfAbsolutePath}");
        }
        $base = rtrim((string) config('atelier.conversion.service_url'), '/');
        $response = Http::timeout((int) config('atelier.conversion.timeout', 300))
            ->attach('file', (string) file_get_contents($pdfAbsolutePath), basename($pdfAbsolutePath))
            ->post("{$base}/render", ['page' => $page, 'dpi' => $dpi]);

        if ($response->failed()) {
            throw new RuntimeException("Render servisi başarısız (HTTP {$response->status()}).");
        }
        return $response->body();
    }

    public function buildDxf(array $polylines): string
    {
        $base = rtrim((string) config('atelier.conversion.service_url'), '/');
        $response = Http::timeout((int) config('atelier.conversion.timeout', 300))
            ->asJson()->post("{$base}/build-dxf", ['polylines' => $polylines]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'DXF üretimi başarısız (HTTP %d): %s',
                $response->status(), substr($response->body(), 0, 300),
            ));
        }
        return (string) ($response->json('dxf') ?? '');
    }
```

- [ ] **Step 5: `MockPdfDxfConverter`'a uygulamaları ekle** (`minimalDxf` öncesi)

```php
    public function renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string
    {
        // 1x1 PNG (servissiz dev/test backdrop'u).
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pPGAAAAAElFTkSuQmCC'
        );
    }

    public function buildDxf(array $polylines): string
    {
        return $this->minimalDxf();
    }
```

- [ ] **Step 6: Testi çalıştır, PASS gör**

Run: `php artisan test --filter=MockConverterTracingTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add Modules/Atelier/Services/Conversion/Contracts/PdfDxfConverterContract.php Modules/Atelier/Services/Conversion/Drivers/HttpPdfDxfConverter.php Modules/Atelier/Services/Conversion/Drivers/MockPdfDxfConverter.php tests/Unit/Atelier/MockConverterTracingTest.php
git commit -m "feat(atelier): converter sözleşmesine renderPage + buildDxf"
```

---

### Task 6: `SaveTracedPatternRequest` doğrulaması

**Files:**
- Create: `Modules/Atelier/Http/Requests/SaveTracedPatternRequest.php`
- Test: `tests/Unit/Atelier/SaveTracedPatternRequestTest.php` (yeni)

**Interfaces:**
- Produces: `SaveTracedPatternRequest` — `rules()` payload `{name, product_type?, size_range?, calibration:{px_per_mm>0, image_height_px>0}, pieces:[{name, quantity?, size?, polylines:[{role in [cut,grain], points:[[x,y],...] min 2}]}]}`; en az 1 `cut` poligonu ≥3 nokta olmalı.

- [ ] **Step 1: Failing test yaz** — `tests/Unit/Atelier/SaveTracedPatternRequestTest.php`

```php
<?php

namespace Tests\Unit\Atelier;

use Illuminate\Support\Facades\Validator;
use Modules\Atelier\Http\Requests\SaveTracedPatternRequest;
use Tests\TestCase;

class SaveTracedPatternRequestTest extends TestCase
{
    private function validate(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $req = new SaveTracedPatternRequest();
        $v = Validator::make($payload, $req->rules());
        $req->withValidator($v);
        return $v;
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Ceket',
            'calibration' => ['px_per_mm' => 3.78, 'image_height_px' => 1200],
            'pieces' => [[
                'name' => 'Ön', 'quantity' => 1,
                'polylines' => [[
                    'role' => 'cut',
                    'points' => [[0, 0], [100, 0], [100, 100]],
                ]],
            ]],
        ];
    }

    public function test_valid_passes(): void
    {
        $this->assertFalse($this->validate($this->validPayload())->fails());
    }

    public function test_missing_calibration_fails(): void
    {
        $p = $this->validPayload();
        unset($p['calibration']);
        $this->assertTrue($this->validate($p)->fails());
    }

    public function test_cut_with_two_points_fails(): void
    {
        $p = $this->validPayload();
        $p['pieces'][0]['polylines'][0]['points'] = [[0, 0], [10, 0]];
        $this->assertTrue($this->validate($p)->fails());
    }
}
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `php artisan test --filter=SaveTracedPatternRequestTest`
Expected: FAIL (sınıf yok)

- [ ] **Step 3: Request sınıfını oluştur** — `Modules/Atelier/Http/Requests/SaveTracedPatternRequest.php`

```php
<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveTracedPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.manage') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'                          => ['required', 'string', 'max:191'],
            'product_type'                  => ['nullable', 'string', 'max:100'],
            'size_range'                    => ['nullable', 'string', 'max:100'],
            'calibration.px_per_mm'         => ['required', 'numeric', 'gt:0'],
            'calibration.image_height_px'   => ['required', 'integer', 'gt:0'],
            'pieces'                        => ['required', 'array', 'min:1'],
            'pieces.*.name'                 => ['required', 'string', 'max:100'],
            'pieces.*.quantity'             => ['nullable', 'integer', 'min:1'],
            'pieces.*.size'                 => ['nullable', 'string', 'max:100'],
            'pieces.*.polylines'            => ['required', 'array', 'min:1'],
            'pieces.*.polylines.*.role'     => ['required', 'string', 'in:cut,grain'],
            'pieces.*.polylines.*.points'   => ['required', 'array', 'min:2'],
            'pieces.*.polylines.*.points.*' => ['array', 'size:2'],
            'pieces.*.polylines.*.points.*.*' => ['numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasClosedCut = false;
            foreach ((array) $this->input('pieces', []) as $piece) {
                foreach ((array) ($piece['polylines'] ?? []) as $pl) {
                    if (($pl['role'] ?? null) === 'cut' && count($pl['points'] ?? []) >= 3) {
                        $hasClosedCut = true;
                    }
                }
            }
            if (! $hasClosedCut) {
                $v->errors()->add('pieces', 'En az bir kesim konturu (cut) ≥3 nokta içermeli.');
            }
        });
    }
}
```

- [ ] **Step 4: Testi çalıştır, PASS gör**

Run: `php artisan test --filter=SaveTracedPatternRequestTest`
Expected: PASS (3 test)

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Http/Requests/SaveTracedPatternRequest.php tests/Unit/Atelier/SaveTracedPatternRequestTest.php
git commit -m "feat(atelier): SaveTracedPatternRequest — izleme payload doğrulaması"
```

---

### Task 7: PatternController uçları + route'lar

**Files:**
- Modify: `Modules/Atelier/Http/Controllers/PatternController.php` (use + `tracer`, `tracerImage`, `saveTraced`)
- Modify: `Modules/Atelier/routes/web.php:103-114` (3 yeni route)
- Test: `tests/Feature/Atelier/PatternTracerTest.php` (yeni)

**Interfaces:**
- Consumes: `applyTracedDxf` (T4), `renderPage`/`buildDxf` (T5), `SaveTracedPatternRequest` (T6), `Pattern::EXTRACTION_NEEDS_TRACING` (T3).
- Produces: route adları `atelier.patterns.tracer`, `atelier.patterns.tracer-image`, `atelier.patterns.traced`.

- [ ] **Step 1: Failing test yaz** — `tests/Feature/Atelier/PatternTracerTest.php`

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternTracerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('atelier.pattern.manage');
        $this->app->bind(PdfDxfConverterContract::class, MockPdfDxfConverter::class);
    }

    private function rasterPattern(): Pattern
    {
        Storage::disk('public')->put('atelier/patterns/r.pdf', '%PDF-1.4');
        return Pattern::create([
            'name' => 'R', 'product_type' => 'belirsiz', 'status' => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING, 'pdf_path' => 'atelier/patterns/r.pdf',
        ]);
    }

    public function test_tracer_requires_permission(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs(User::factory()->create())
            ->get("/atelier/patterns/{$p->id}/tracer")
            ->assertForbidden();
    }

    public function test_tracer_image_returns_png(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->get("/atelier/patterns/{$p->id}/tracer-image?page=0")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_save_traced_marks_done_with_dxf(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->post("/atelier/patterns/{$p->id}/traced", [
                'name' => 'Ceket', 'product_type' => 'ceket', 'size_range' => '38-44',
                'calibration' => ['px_per_mm' => 3.78, 'image_height_px' => 1200],
                'pieces' => [[
                    'name' => 'Ön', 'quantity' => 1, 'size' => '38',
                    'polylines' => [['role' => 'cut', 'points' => [[0, 0], [100, 0], [100, 100]]]],
                ]],
            ])
            ->assertRedirect(route('atelier.patterns.index'));

        $fresh = $p->fresh('parts');
        $this->assertSame(Pattern::EXTRACTION_DONE, $fresh->extraction_status);
        $this->assertNotNull($fresh->dxf_path);
        $this->assertSame(1, $fresh->parts->count());
    }

    public function test_save_traced_validation_fails_without_calibration(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->post("/atelier/patterns/{$p->id}/traced", [
                'name' => 'Ceket',
                'pieces' => [['name' => 'Ön', 'polylines' => [['role' => 'cut', 'points' => [[0, 0], [1, 0], [1, 1]]]]]],
            ])
            ->assertSessionHasErrors('calibration.px_per_mm');
    }
}
```

- [ ] **Step 2: Testi çalıştır, FAIL gör**

Run: `php artisan test --filter=PatternTracerTest`
Expected: FAIL (route yok)

- [ ] **Step 3: Controller'a use + metodları ekle** — `PatternController.php`

Üst `use` bloğuna ekle:
```php
use Modules\Atelier\Http\Requests\SaveTracedPatternRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
```
Sınıfa (örn. `retryExtraction` ardına) ekle:
```php
    public function tracer(Pattern $pattern): Response
    {
        abort_unless(
            $pattern->pdf_path && $pattern->extraction_status === Pattern::EXTRACTION_NEEDS_TRACING,
            404,
        );

        return Inertia::render('Atelier::RasterTracer', [
            'pattern' => [
                'id'          => $pattern->id,
                'name'        => $pattern->name,
                'productType' => $pattern->product_type,
                'sizeRange'   => $pattern->size_range,
            ],
            'imageBase' => route('atelier.patterns.tracer-image', ['pattern' => $pattern->id]),
        ]);
    }

    public function tracerImage(Request $request, Pattern $pattern): HttpResponse
    {
        abort_unless((bool) $pattern->pdf_path, 404);
        $page = max(0, (int) $request->query('page', 0));
        $dpi  = min(300, max(72, (int) $request->query('dpi', 200)));
        $abs  = Storage::disk('public')->path($pattern->pdf_path);

        try {
            $png = $this->converter->renderPage($abs, $page, $dpi);
        } catch (\Throwable $e) {
            abort(502, 'Sayfa render edilemedi: ' . $e->getMessage());
        }

        return response($png, 200)->header('Content-Type', 'image/png');
    }

    public function saveTraced(SaveTracedPatternRequest $request, Pattern $pattern): RedirectResponse
    {
        abort_unless($pattern->extraction_status === Pattern::EXTRACTION_NEEDS_TRACING, 404);
        $v   = $request->validated();
        $ppm = (float) $v['calibration']['px_per_mm'];
        $h   = (int) $v['calibration']['image_height_px'];

        $polylines = [];
        $parts     = [];
        foreach ($v['pieces'] as $piece) {
            foreach ($piece['polylines'] as $pl) {
                $mm = [];
                foreach ($pl['points'] as $pt) {
                    $mm[] = [round(((float) $pt[0]) / $ppm, 3), round(($h - (float) $pt[1]) / $ppm, 3)];
                }
                $polylines[] = ['role' => $pl['role'], 'points' => $mm, 'closed' => $pl['role'] === 'cut'];
            }
            $parts[] = [
                'part_name'  => $piece['name'],
                'quantity'   => $piece['quantity'] ?? 1,
                'size_range' => $piece['size'] ?? null,
            ];
        }

        try {
            $dxf = $this->converter->buildDxf($polylines);
        } catch (\Throwable $e) {
            return back()->withErrors(['trace' => 'DXF üretilemedi: ' . $e->getMessage()]);
        }

        $this->library->applyTracedDxf($pattern, $dxf, [
            'name'         => $v['name'],
            'product_type' => $v['product_type'] ?? $pattern->product_type,
            'size_range'   => $v['size_range'] ?? null,
        ], $parts);

        return redirect()->route('atelier.patterns.index')->with('success', 'Kalıp sayısallaştırıldı.');
    }
```

- [ ] **Step 4: Route'ları ekle** — `Modules/Atelier/routes/web.php`, `patterns/{pattern}/retry-extraction` satırının ardına

```php
        Route::get('patterns/{pattern}/tracer', [\Modules\Atelier\Http\Controllers\PatternController::class, 'tracer'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer');
        Route::get('patterns/{pattern}/tracer-image', [\Modules\Atelier\Http\Controllers\PatternController::class, 'tracerImage'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer-image');
        Route::post('patterns/{pattern}/traced', [\Modules\Atelier\Http\Controllers\PatternController::class, 'saveTraced'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.traced');
```

- [ ] **Step 5: Testi çalıştır, PASS gör**

Run: `php artisan test --filter=PatternTracerTest`
Expected: PASS (4 test)

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/Http/Controllers/PatternController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/PatternTracerTest.php
git commit -m "feat(atelier): tracer/tracerImage/saveTraced uçları + route'lar"
```

---

### Task 8: `RasterTracer.vue` editörü + Patterns'a "Sayısallaştır" butonu

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/RasterTracer.vue`
- Modify: `Modules/Atelier/Resources/assets/js/Pages/Patterns.vue` (needs_tracing kalıpta buton)
- Test: Manuel kabul (proje JS test çatısı yok)

**Interfaces:**
- Consumes: props `pattern{id,name,productType,sizeRange}`, `imageBase` (T7 `tracer`); POST `imageBase?page=N` (PNG), POST `/atelier/patterns/{id}/traced` (T7 payload).

- [ ] **Step 1: `RasterTracer.vue`'yu oluştur**

```vue
<template>
  <div>
    <Head :title="`Sayısallaştır — ${pattern.name}`" />
    <AtelierNav />
    <div class="p-4 space-y-3">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-lg font-semibold">Sayısallaştır: {{ pattern.name }}</h1>
        <span class="text-sm text-gray-500">Sayfa {{ page + 1 }} / {{ pageCount || '?' }}</span>
        <button class="btn" :disabled="page === 0" @click="changePage(page - 1)">◀ Önceki</button>
        <button class="btn" :disabled="pageCount && page >= pageCount - 1" @click="changePage(page + 1)">Sonraki ▶</button>
        <label class="text-sm">DPI
          <select v-model.number="dpi" @change="loadImage" class="border rounded px-1">
            <option :value="150">150</option><option :value="200">200</option><option :value="300">300</option>
          </select>
        </label>
      </div>

      <div class="flex gap-2 items-center text-sm flex-wrap">
        <span class="font-medium">Araç:</span>
        <button class="btn" :class="{ 'bg-blue-600 text-white': tool === 'calibrate' }" @click="tool = 'calibrate'">📏 Kalibrasyon</button>
        <button class="btn" :class="{ 'bg-blue-600 text-white': tool === 'trace' }" @click="tool = 'trace'">✏️ İzle</button>
        <span v-if="pxPerMm" class="text-green-700">Ölçek: {{ pxPerMm.toFixed(3) }} px/mm</span>
        <span v-else class="text-red-600">Ölçek henüz ayarlanmadı</span>
      </div>

      <div class="border rounded overflow-auto bg-gray-100" style="max-height:70vh">
        <svg v-if="imgUrl" :width="imgW" :height="imgH" @click="onSvgClick" style="display:block">
          <image :href="imgUrl" :width="imgW" :height="imgH" />
          <!-- kalibrasyon çizgisi -->
          <line v-if="calib.a && calib.b" :x1="calib.a.x" :y1="calib.a.y" :x2="calib.b.x" :y2="calib.b.y"
                stroke="red" stroke-width="2" />
          <!-- aktif izleme -->
          <polyline v-if="current.points.length" :points="ptsStr(current.points)"
                    fill="none" stroke="#2563eb" stroke-width="2" />
          <circle v-for="(p, i) in current.points" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#2563eb" />
          <!-- kaydedilmiş parçalar -->
          <polygon v-for="(pc, i) in pieces" :key="'pc' + i" :points="pieceStr(pc)"
                   fill="rgba(16,185,129,0.15)" stroke="#059669" stroke-width="2" />
        </svg>
        <div v-else class="p-8 text-center text-gray-500">Sayfa yükleniyor…</div>
      </div>

      <!-- aktif parça paneli -->
      <div v-if="tool === 'trace'" class="flex gap-2 items-end flex-wrap border-t pt-2">
        <label class="text-sm">Parça adı<input v-model="current.name" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Adet<input v-model.number="current.quantity" type="number" min="1" class="border rounded px-2 py-1 block w-20" /></label>
        <label class="text-sm">Beden<input v-model="current.size" class="border rounded px-2 py-1 block w-28" /></label>
        <button class="btn" @click="closeCurrentPiece" :disabled="current.points.length < 3">✓ Parçayı bitir</button>
        <button class="btn" @click="current.points = []" :disabled="!current.points.length">Temizle</button>
      </div>

      <ul class="text-sm list-disc pl-5">
        <li v-for="(pc, i) in pieces" :key="'l' + i">{{ pc.name }} ({{ pc.quantity }}x{{ pc.size ? ', ' + pc.size : '' }})
          <button class="text-red-600 ml-2" @click="pieces.splice(i, 1)">sil</button></li>
      </ul>

      <div class="flex gap-2 items-end border-t pt-2 flex-wrap">
        <label class="text-sm">Kalıp adı<input v-model="meta.name" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Ürün tipi<input v-model="meta.product_type" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Beden aralığı<input v-model="meta.size_range" class="border rounded px-2 py-1 block" /></label>
        <button class="btn bg-green-600 text-white" :disabled="!canSave" @click="save">💾 Kaydet (DXF)</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })
const props = defineProps({ pattern: Object, imageBase: String })

const page = ref(0)
const dpi = ref(200)
const pageCount = ref(0)
const imgUrl = ref('')
const imgW = ref(0)
const imgH = ref(0)
const tool = ref('calibrate')
const pxPerMm = ref(null)
const calib = reactive({ a: null, b: null })
const current = reactive({ points: [], name: '', quantity: 1, size: '' })
const pieces = ref([])
const meta = reactive({
  name: props.pattern.name, product_type: props.pattern.productType || '', size_range: props.pattern.sizeRange || '',
})

const canSave = computed(() => pxPerMm.value && pieces.value.length > 0)

function ptsStr(pts) { return pts.map(p => `${p.x},${p.y}`).join(' ') }
function pieceStr(pc) { return pc.points.map(p => `${p.x},${p.y}`).join(' ') }

async function loadImage() {
  imgUrl.value = ''
  const url = `${props.imageBase}?page=${page.value}&dpi=${dpi.value}`
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  if (!res.ok) { pageCount.value = page.value; page.value = Math.max(0, page.value - 1); return }
  pageCount.value = parseInt(res.headers.get('X-Page-Count') || '1', 10)
  imgW.value = parseInt(res.headers.get('X-Width') || '0', 10)
  imgH.value = parseInt(res.headers.get('X-Height') || '0', 10)
  const blob = await res.blob()
  imgUrl.value = URL.createObjectURL(blob)
}

function changePage(p) {
  if (p < 0) return
  page.value = p
  // sayfa başına kalibrasyon ve aktif izleme sıfırlanır
  pxPerMm.value = null; calib.a = null; calib.b = null; current.points = []
  loadImage()
}

function onSvgClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const x = e.clientX - rect.left
  const y = e.clientY - rect.top
  if (tool.value === 'calibrate') {
    if (!calib.a || (calib.a && calib.b)) { calib.a = { x, y }; calib.b = null }
    else {
      calib.b = { x, y }
      const dpx = Math.hypot(calib.b.x - calib.a.x, calib.b.y - calib.a.y)
      const mm = parseFloat(prompt('Bu çizginin gerçek uzunluğu (mm):', '100'))
      if (mm > 0) pxPerMm.value = dpx / mm
    }
  } else if (tool.value === 'trace') {
    current.points.push({ x, y })
  }
}

function closeCurrentPiece() {
  if (current.points.length < 3) return
  pieces.value.push({
    name: current.name || `Parça ${pieces.value.length + 1}`,
    quantity: current.quantity || 1, size: current.size || '',
    points: current.points.slice(),
  })
  current.points = []; current.name = ''; current.size = ''
}

function save() {
  if (!canSave.value) return
  router.post(`/atelier/patterns/${props.pattern.id}/traced`, {
    name: meta.name, product_type: meta.product_type, size_range: meta.size_range,
    calibration: { px_per_mm: pxPerMm.value, image_height_px: imgH.value },
    pieces: pieces.value.map(pc => ({
      name: pc.name, quantity: pc.quantity, size: pc.size,
      polylines: [{ role: 'cut', points: pc.points.map(p => [p.x, p.y]) }],
    })),
  }, { preserveScroll: true })
}

onMounted(loadImage)
</script>
```
> `.btn` sınıfı projedeki mevcut buton stilini varsayar; yoksa `class="px-2 py-1 border rounded"` ile değiştir.

- [ ] **Step 2: Patterns.vue'ya "Sayısallaştır" butonu ekle**

`Patterns.vue`'da kalıp kartında, `extraction_status`/`extractionStatus` kullanılan yere (retry-extraction butonunun yanına) ekle:
```vue
<button
  v-if="p.extractionStatus === 'needs_tracing'"
  class="px-2 py-1 text-sm rounded bg-amber-500 text-white"
  @click="router.get(`/atelier/patterns/${p.id}/tracer`)"
>Sayısallaştır</button>
```
> `router` zaten `Patterns.vue`'da `@inertiajs/vue3`'ten import edilidir (mevcut import'u doğrula; yoksa ekle).

- [ ] **Step 3: Frontend'i derle**

Run: `npm run build`
Expected: hatasız derleme (RasterTracer.vue glob ile bulunur)

- [ ] **Step 4: Manuel kabul**

1. İki terminal aç: Python servisi (`python -m uvicorn main:app --app-dir Modules/Atelier/python/pdf_dxf_service --host 127.0.0.1 --port 8200`) + `php artisan queue:work --queue=default`.
2. Patterns ekranından bir **raster** PDF (ör. ШиК) yükle → kalıp `needs_tracing` olarak listelenir, "Sayısallaştır" butonu çıkar.
3. Butona bas → editör açılır, sayfa görüntüsü yüklenir.
4. **Kalibrasyon:** bilinen bir mesafeyi (cetvel/kontrol karesi) çiz, mm gir → "Ölçek: N px/mm" görünür.
5. **İzle** aracına geç, bir parçayı tıklayarak çiz (≥3 nokta), ad/adet/beden gir, "Parçayı bitir".
6. "Kaydet (DXF)" → Patterns'a döner, kalıp `done` olur, `dxfUrl` dolu.
7. DXF'i bir CAD'de aç → kalibre edilen mesafenin gerçekte doğru mm olduğunu doğrula.

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/RasterTracer.vue Modules/Atelier/Resources/assets/js/Pages/Patterns.vue
git commit -m "feat(atelier): RasterTracer editörü + Patterns sayısallaştır butonu"
```

---

## Self-Review

**Spec coverage:**
- §4 RasterTracer.vue → T8 ✓; /render → T2 ✓; /build-dxf → T1 ✓; tracer/tracerImage/saveTraced → T7 ✓; applyTracedDxf → T4 ✓; markNeedsTracing → T3 ✓; EXTRACTION_NEEDS_TRACING → T3 ✓; SaveTracedPatternRequest → T6 ✓; route'lar → T7 ✓.
- §5 import yönlendirme → T3 (importPdf raster→needs_tracing) ✓; px→mm + y-flip → T7 saveTraced ✓.
- §6 servis kapalı hata → T5/T7 try-catch ✓; kalibrasyon/poligon doğrulama → T6 ✓; idempotentlik → T4 (deleteFile/syncParts) ✓; yetki → T7 ✓.
- §7 testler → T1–T7 her birinde ✓; manuel kabul → T8 ✓.
- **`/detect-scale` (spec §4 best-effort/opsiyonel):** v1'de KAPSAM DIŞI. Spec onu açıkça "best-effort, bulunamazsa manuel kalibrasyona düşülür" olarak işaretliyor; v1 yalnızca manuel kalibrasyon (T8) ile gönderiliyor. Otomatik tespit, gerçek örnek taramalarla ayrı bir faz gerektirir. Bilinçli erteleme.

**Placeholder taraması:** Tüm adımlarda gerçek kod var; TBD/TODO yok.

**Tip tutarlılığı:** `renderPage(string,int,int):string`, `buildDxf(array):string` T5'te tanımlı, T7'de aynı imzayla çağrılıyor ✓. `applyTracedDxf(Pattern,string,array,array)` T4 tanımı = T7 çağrısı ✓. `createRasterDraft`/`markNeedsTracing` T3 tanımı = importPdf kullanımı ✓. Payload anahtarları (`calibration.px_per_mm`, `calibration.image_height_px`, `pieces[].polylines[].role/points`) T6 kuralları = T7 işleme = T8 gönderim arasında tutarlı ✓.
