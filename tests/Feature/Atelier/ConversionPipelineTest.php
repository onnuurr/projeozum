<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\ConversionPipelineService;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Tests\TestCase;

class ConversionPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['atelier.conversion.disk' => 'public']);
        $this->app->bind(PdfDxfConverterContract::class, MockPdfDxfConverter::class);
    }

    private function pipeline(): ConversionPipelineService
    {
        return app(ConversionPipelineService::class);
    }

    public function test_submit_stores_pdf_and_creates_pending_job(): void
    {
        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kalip.pdf', 100, 'application/pdf'));

        $this->assertSame(ConversionJob::STATUS_PENDING, $job->status);
        Storage::disk('public')->assertExists($job->source_pdf_path);
    }

    public function test_process_green_produces_dxf_and_needs_review(): void
    {
        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kalip.pdf', 100, 'application/pdf'));

        $job = $this->pipeline()->process($job);

        $this->assertSame(ConversionJob::STATUS_NEEDS_REVIEW, $job->status);
        $this->assertSame(ConversionJob::CLASS_GREEN, $job->classification);
        $this->assertNotNull($job->output_dxf_path);
        Storage::disk('public')->assertExists($job->output_dxf_path);
        $this->assertSame('tulum', $job->error_report['metadata']['product_type']);
    }

    public function test_process_red_result_lands_in_review_without_dxf(): void
    {
        // Kırmızı (DXF üretilemedi) ama istisna yok → needs_review; operatör reddeder.
        $this->app->bind(PdfDxfConverterContract::class, fn () => new class implements PdfDxfConverterContract {
            public function convert(string $pdfAbsolutePath): \Modules\Atelier\Services\Conversion\ConversionResult
            {
                return new \Modules\Atelier\Services\Conversion\ConversionResult(
                    classification: ConversionJob::CLASS_RED,
                    confidence: 30.0,
                    dxf: null,
                    metadata: [],
                    errors: ['Izgara okunamadı'],
                );
            }

            public function probe(string $pdfAbsolutePath): ?array
            {
                return null;
            }
        });

        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kalip.pdf', 100, 'application/pdf'));
        $job = $this->pipeline()->process($job);

        $this->assertSame(ConversionJob::STATUS_NEEDS_REVIEW, $job->status);
        $this->assertSame(ConversionJob::CLASS_RED, $job->classification);
        $this->assertNull($job->output_dxf_path);
        $this->assertContains('Izgara okunamadı', $job->error_report['errors']);
    }

    public function test_process_exception_marks_failed(): void
    {
        $this->app->bind(PdfDxfConverterContract::class, fn () => new class implements PdfDxfConverterContract {
            public function convert(string $pdfAbsolutePath): \Modules\Atelier\Services\Conversion\ConversionResult
            {
                throw new \RuntimeException('servis çöktü');
            }

            public function probe(string $pdfAbsolutePath): ?array
            {
                return null;
            }
        });

        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kalip.pdf', 100, 'application/pdf'));

        try {
            $this->pipeline()->process($job);
            $this->fail('İstisna bekleniyordu (kuyruk yeniden denesin diye yutulmaz).');
        } catch (\RuntimeException $e) {
            $this->assertSame('servis çöktü', $e->getMessage());
        }

        $this->assertSame(ConversionJob::STATUS_FAILED, $job->fresh()->status);
        $this->assertSame(ConversionJob::CLASS_RED, $job->fresh()->classification);
    }

    public function test_approve_creates_pattern_with_parts_and_links_job(): void
    {
        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kapusonlu.pdf', 100, 'application/pdf'));
        $job = $this->pipeline()->process($job);

        $pattern = $this->pipeline()->approve($job, reviewerId: null);

        $this->assertInstanceOf(Pattern::class, $pattern);
        $this->assertSame(Pattern::STATUS_APPROVED, $pattern->status);
        $this->assertSame('tulum', $pattern->product_type);
        $this->assertCount(3, $pattern->parts);
        $this->assertSame($job->output_dxf_path, $pattern->dxf_path);
        $this->assertSame($job->source_pdf_path, $pattern->pdf_path);

        $job->refresh();
        $this->assertSame(ConversionJob::STATUS_APPROVED, $job->status);
        $this->assertSame($pattern->id, $job->pattern_id);
        $this->assertNotNull($job->reviewed_at);
    }

    public function test_reject_closes_job_without_pattern(): void
    {
        $job = $this->pipeline()->submit(UploadedFile::fake()->create('kalip.pdf', 100, 'application/pdf'));
        $job = $this->pipeline()->process($job);

        $job = $this->pipeline()->reject($job, reviewerId: null);

        $this->assertSame(ConversionJob::STATUS_REJECTED, $job->status);
        $this->assertSame(0, Pattern::count());
    }
}
