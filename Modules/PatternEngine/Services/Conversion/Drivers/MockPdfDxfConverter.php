<?php

namespace Modules\Atelier\Services\Conversion\Drivers;

use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Services\Conversion\ConversionResult;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;

/**
 * Servissiz dev/test için sahte dönüştürücü.
 *
 * Geçerli (minimal) bir DXF + örnek metadata döndürür; gerçek geometri çıkarmaz.
 * Dosya adında "bozuk" geçerse kırmızı/sarı triyajı simüle eder (UI testleri için).
 */
class MockPdfDxfConverter implements PdfDxfConverterContract
{
    public function convert(string $pdfAbsolutePath): ConversionResult
    {
        $name = pathinfo($pdfAbsolutePath, PATHINFO_FILENAME);

        if (str_contains(mb_strtolower($name), 'bozuk')) {
            return new ConversionResult(
                classification: ConversionJob::CLASS_RED,
                confidence: 32.0,
                dxf: null,
                metadata: [],
                errors: ['Izgara okunamadı', 'Kalıp rengi ayrıştırılamadı'],
            );
        }

        return new ConversionResult(
            classification: ConversionJob::CLASS_GREEN,
            confidence: 94.0,
            dxf: $this->minimalDxf(),
            metadata: [
                'name'               => $name,
                'product_type'       => 'tulum',
                'size_range'         => '116-122-128-134',
                'scale_verified'     => true,
                'scale_deviation_mm' => 0.3,
                'grid'               => ['rows' => 5, 'cols' => 5],
                'parts'              => [
                    ['part_name' => 'Ön', 'quantity' => 1],
                    ['part_name' => 'Arka', 'quantity' => 1],
                    ['part_name' => 'Kol', 'quantity' => 2],
                ],
            ],
            errors: [],
        );
    }

    public function convertPhoto(string $photoAbsolutePath, float $markerMm = 40.0): ConversionResult
    {
        $name = pathinfo($photoAbsolutePath, PATHINFO_FILENAME);

        if (str_contains(mb_strtolower($name), 'markersiz')) {
            return new ConversionResult(
                classification: ConversionJob::CLASS_RED,
                confidence: 0.0,
                dxf: null,
                metadata: ['name' => $name, 'source' => 'photo'],
                errors: ['Referans marker bulunamadı — kalıp kağıdının yanına marker koyup yeniden çekin.'],
            );
        }

        return new ConversionResult(
            classification: ConversionJob::CLASS_YELLOW,
            confidence: 55.0,
            dxf: $this->minimalDxf(),
            metadata: [
                'name'               => $name,
                'source'             => 'photo',
                'scale_verified'     => true,
                'scale_deviation_mm' => null,
                'marker_mm'          => $markerMm,
                'parts'              => [],
            ],
            errors: ['Fotoğraftan otomatik vektörleştirme — parça/beden ayrımı operatör tarafından doğrulanmalı.'],
        );
    }

    public function probe(string $pdfAbsolutePath): ?array
    {
        // Mock ön-kontrol yapmaz → null = "atla, akışı engelleme" (servissiz dev).
        return null;
    }

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

    /** Geçerli, boş bir DXF iskeleti (yalnız mock için). */
    private function minimalDxf(): string
    {
        return "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n";
    }
}
