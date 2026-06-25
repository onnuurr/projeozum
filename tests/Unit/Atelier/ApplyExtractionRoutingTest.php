<?php

namespace Tests\Unit\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\ConversionResult;
use Modules\Atelier\Services\PatternLibraryService;
use Tests\TestCase;

class ApplyExtractionRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): Pattern
    {
        return Pattern::create([
            'name' => 'T', 'product_type' => 'belirsiz', 'status' => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_PROCESSING, 'pdf_path' => 'atelier/patterns/x.pdf',
        ]);
    }

    public function test_red_raster_falls_back_to_needs_tracing(): void
    {
        Storage::fake('public');
        $p = $this->draft();
        $result = new ConversionResult('red', 0.0, null, ['source' => 'raster'], ['Çizgi yok']);

        app(PatternLibraryService::class)->applyExtraction($p, $result);

        $this->assertSame(Pattern::EXTRACTION_NEEDS_TRACING, $p->fresh()->extraction_status);
    }

    public function test_red_vector_marks_failed(): void
    {
        Storage::fake('public');
        $p = $this->draft();
        $result = new ConversionResult('red', 0.0, null, ['profile_candidates' => []], ['Profil yok']);

        app(PatternLibraryService::class)->applyExtraction($p, $result);

        $this->assertSame(Pattern::EXTRACTION_FAILED, $p->fresh()->extraction_status);
    }

    public function test_yellow_raster_marks_done_with_layers(): void
    {
        Storage::fake('public');
        $p = $this->draft();
        $result = new ConversionResult('yellow', 45.0, "0\nSECTION\n0\nEOF\n", [
            'source' => 'raster', 'name' => 'ShiK', 'size_layers' => ['BEDEN_CYAN', 'BEDEN_KIRMIZI'],
            'scale_verified' => false,
        ], ['operatör doğrulamalı']);

        app(PatternLibraryService::class)->applyExtraction($p, $result);

        $fresh = $p->fresh();
        $this->assertSame(Pattern::EXTRACTION_DONE, $fresh->extraction_status);
        $this->assertNotNull($fresh->dxf_path);
        $this->assertSame(['BEDEN_CYAN', 'BEDEN_KIRMIZI'], $fresh->size_layers);
    }
}
