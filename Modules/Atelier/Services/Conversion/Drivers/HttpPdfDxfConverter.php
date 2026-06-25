<?php

namespace Modules\Atelier\Services\Conversion\Drivers;

use Illuminate\Support\Facades\Http;
use Modules\Atelier\Services\Conversion\ConversionResult;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use RuntimeException;

/**
 * PDF'i FastAPI mikroservisine (PyMuPDF + ezdxf) gönderip DXF + sınıflandırma alır.
 */
class HttpPdfDxfConverter implements PdfDxfConverterContract
{
    public function convert(string $pdfAbsolutePath): ConversionResult
    {
        if (! is_file($pdfAbsolutePath)) {
            throw new RuntimeException("Dönüştürülecek PDF bulunamadı: {$pdfAbsolutePath}");
        }

        $base = rtrim((string) config('atelier.conversion.service_url'), '/');

        $response = Http::timeout((int) config('atelier.conversion.timeout', 300))
            ->attach('file', (string) file_get_contents($pdfAbsolutePath), basename($pdfAbsolutePath))
            ->post("{$base}/convert");

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'PDF→DXF servisi başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return ConversionResult::fromResponse($response->json() ?? []);
    }

    public function probe(string $pdfAbsolutePath): ?array
    {
        if (! is_file($pdfAbsolutePath)) {
            return null;
        }

        $base = rtrim((string) config('atelier.conversion.service_url'), '/');

        try {
            $response = Http::timeout(20)
                ->attach('file', (string) file_get_contents($pdfAbsolutePath), basename($pdfAbsolutePath))
                ->post("{$base}/probe");
        } catch (\Throwable $e) {
            return null; // servis kapalı/ulaşılamıyor → ön-kontrolü atla, akışı engelleme
        }

        if ($response->failed()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) && isset($json['kind']) ? $json : null;
    }

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
}
