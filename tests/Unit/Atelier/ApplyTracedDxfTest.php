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
