<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Tests\TestCase;

class PatternLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(array $overrides = []): Pattern
    {
        return Pattern::create(array_merge([
            'name'         => 'Çocuk Tulum',
            'product_type' => 'tulum',
            'size_range'   => '116-122-128-134',
            'status'       => Pattern::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_pattern_has_parts_and_tags(): void
    {
        $pattern = $this->pattern();
        $pattern->parts()->create(['part_name' => 'Kol', 'quantity' => 2, 'size_range' => '116-134']);
        $pattern->parts()->create(['part_name' => 'Kapüşon', 'quantity' => 1]);
        $pattern->tags()->create(['tag' => 'kışlık']);

        $fresh = $pattern->fresh();
        $this->assertCount(2, $fresh->parts);
        $this->assertSame(2, $fresh->parts->firstWhere('part_name', 'Kol')->quantity);
        $this->assertSame('kışlık', $fresh->tags->first()->tag);
    }

    public function test_deleting_pattern_cascades_parts_and_tags(): void
    {
        $pattern = $this->pattern();
        $pattern->parts()->create(['part_name' => 'Ön']);
        $pattern->tags()->create(['tag' => 'pamuk']);

        $pattern->forceDelete();

        $this->assertDatabaseMissing('pattern_parts', ['pattern_id' => $pattern->id]);
        $this->assertDatabaseMissing('pattern_tags', ['pattern_id' => $pattern->id]);
    }

    public function test_design_card_concept_first_starts_without_pattern(): void
    {
        // Akış Y: önce konsept, pattern_id sonradan eşleşince dolar.
        $card = DesignCard::create([
            'source'           => DesignCard::SOURCE_CONCEPT_FIRST,
            'prompt'           => 'lacivert çiçekli kışlık tulum',
            'product_type'     => 'tulum',
            'generated_images' => ['concepts/a.png', 'concepts/b.png'],
            'status'           => DesignCard::STATUS_GENERATED,
        ]);

        $this->assertNull($card->pattern_id);
        $this->assertSame(['concepts/a.png', 'concepts/b.png'], $card->fresh()->generated_images);

        // Eşleme: kullanıcı önerilen kalıbı seçer.
        $pattern = $this->pattern();
        $card->update(['pattern_id' => $pattern->id, 'status' => DesignCard::STATUS_MATCHED]);

        $this->assertSame($pattern->id, $card->fresh()->pattern->id);
    }

    public function test_conversion_job_links_to_resulting_pattern(): void
    {
        $job = ConversionJob::create([
            'source_pdf_path'  => 'uploads/raw/001.pdf',
            'classification'   => ConversionJob::CLASS_GREEN,
            'confidence_score' => 92.5,
            'status'           => ConversionJob::STATUS_NEEDS_REVIEW,
            'error_report'     => ['grid' => 'ok', 'warnings' => []],
        ]);

        $this->assertSame(['grid' => 'ok', 'warnings' => []], $job->fresh()->error_report);

        // Onay: üretilen kalıba bağlanır.
        $pattern = $this->pattern(['status' => Pattern::STATUS_APPROVED, 'dxf_path' => 'out/001.dxf']);
        $job->update([
            'status'          => ConversionJob::STATUS_APPROVED,
            'output_dxf_path' => 'out/001.dxf',
            'pattern_id'      => $pattern->id,
        ]);

        $this->assertSame($pattern->id, $job->fresh()->pattern->id);
        $this->assertTrue($pattern->conversionJobs()->whereKey($job->id)->exists());
    }
}
