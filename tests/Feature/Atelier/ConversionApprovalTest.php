<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Services\Conversion\ConversionPipelineService;
use Tests\TestCase;

class ConversionApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_extraction_metadata_onto_the_approved_pattern(): void
    {
        $job = ConversionJob::create([
            'source_pdf_path' => 'atelier/conversions/pdf/latzee.pdf',
            'output_dxf_path' => 'atelier/conversions/dxf/latzee.dxf',
            'classification'  => ConversionJob::CLASS_YELLOW,
            'status'          => ConversionJob::STATUS_NEEDS_REVIEW,
            'error_report'    => ['metadata' => [
                'name'         => 'Latzee',
                'profile'      => 'nipnaps',
                'product_type' => 'tulum',
                'size_range'   => '50/56-170/176',
                'size_layers'  => ['BEDEN_YESIL', 'BEDEN_CYAN'],
                'measurements' => ['labels' => ['OW'], 'matrix' => ['OW' => [22.5]]],
                'fabric_usage' => null,
                'parts'        => [
                    ['part_name' => 'Arka', 'quantity' => 1],
                    ['part_name' => 'Askı', 'quantity' => 2],
                ],
            ]],
        ]);

        $pattern = app(ConversionPipelineService::class)->approve($job);

        $this->assertSame('nipnaps', $pattern->vendor);
        $this->assertSame(['BEDEN_YESIL', 'BEDEN_CYAN'], $pattern->size_layers);
        $this->assertSame([22.5], $pattern->measurements['matrix']['OW']);
        $this->assertCount(2, $pattern->parts);
        $this->assertSame(2, $pattern->parts->firstWhere('part_name', 'Askı')->quantity);
        $this->assertSame(ConversionJob::STATUS_APPROVED, $job->fresh()->status);
    }
}
