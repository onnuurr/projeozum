<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Jobs\ExtractPatternFromPdfJob;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\ConversionResult;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Modules\Atelier\Services\PatternLibraryService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternPdfImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->app->bind(PdfDxfConverterContract::class, MockPdfDxfConverter::class);
    }

    private function library(): PatternLibraryService
    {
        return app(PatternLibraryService::class);
    }

    public function test_create_pdf_draft_makes_processing_draft(): void
    {
        $pattern = $this->library()->createPdfDraft(
            UploadedFile::fake()->create('kapusonlu_tulum.pdf', 100, 'application/pdf')
        );

        $this->assertSame(Pattern::STATUS_DRAFT, $pattern->status);
        $this->assertSame(Pattern::EXTRACTION_PROCESSING, $pattern->extraction_status);
        $this->assertSame('kapusonlu_tulum', $pattern->name);
        Storage::disk('public')->assertExists($pattern->pdf_path);
    }

    public function test_apply_extraction_fills_metadata_parts_and_dxf(): void
    {
        $pattern = $this->library()->createPdfDraft(UploadedFile::fake()->create('k.pdf', 100, 'application/pdf'));

        $result = new ConversionResult(
            classification: 'green',
            confidence: 94,
            dxf: "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n",
            metadata: [
                'product_type' => 'tulum', 'size_range' => '116-122-128-134',
                'scale_verified' => true, 'scale_deviation_mm' => 0.3,
                'parts' => [['part_name' => 'Ön', 'quantity' => 1], ['part_name' => 'Kol', 'quantity' => 2]],
            ],
        );

        $pattern = $this->library()->applyExtraction($pattern, $result);

        $this->assertSame(Pattern::EXTRACTION_DONE, $pattern->extraction_status);
        $this->assertSame('tulum', $pattern->product_type);
        $this->assertSame('116-122-128-134', $pattern->size_range);
        $this->assertTrue($pattern->scale_verified);
        $this->assertNotNull($pattern->dxf_path);
        Storage::disk('public')->assertExists($pattern->dxf_path);
        $this->assertCount(2, $pattern->parts);
    }

    public function test_no_dxf_result_marks_failed_not_done(): void
    {
        // Raster/desteklenmeyen PDF → converter red + DXF yok → 'done' DEĞİL 'failed'.
        $pattern = $this->library()->createPdfDraft(UploadedFile::fake()->create('raster.pdf', 100, 'application/pdf'));
        $result = new ConversionResult(
            classification: 'red', confidence: 8, dxf: null,
            metadata: ['name' => 'raster'],
            errors: ['Vektör kalıp çizgisi bulunamadı (taranmış olabilir).'],
        );

        $this->library()->applyExtraction($pattern, $result);

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_FAILED, $pattern->extraction_status);
        $this->assertStringContainsString('taranmış', $pattern->extraction_error);
    }

    public function test_job_handle_completes_draft(): void
    {
        $pattern = $this->library()->createPdfDraft(UploadedFile::fake()->create('k.pdf', 100, 'application/pdf'));

        (new ExtractPatternFromPdfJob($pattern->id))
            ->handle(app(PdfDxfConverterContract::class), $this->library());

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_DONE, $pattern->extraction_status);
        $this->assertSame('tulum', $pattern->product_type); // mock çıktısı
    }

    public function test_extraction_failure_marks_failed(): void
    {
        $this->app->bind(PdfDxfConverterContract::class, fn () => new class implements PdfDxfConverterContract {
            public function convert(string $pdfAbsolutePath): ConversionResult
            {
                throw new \RuntimeException('servis kapalı');
            }

            public function probe(string $pdfAbsolutePath): ?array
            {
                return null;
            }
        });

        $pattern = $this->library()->createPdfDraft(UploadedFile::fake()->create('k.pdf', 100, 'application/pdf'));

        try {
            (new ExtractPatternFromPdfJob($pattern->id))
                ->handle(app(PdfDxfConverterContract::class), $this->library());
            $this->fail('İstisna bekleniyordu.');
        } catch (\RuntimeException $e) {
            $this->assertSame('servis kapalı', $e->getMessage());
        }

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_FAILED, $pattern->extraction_status);
        $this->assertStringContainsString('servis kapalı', $pattern->extraction_error);
    }

    public function test_import_endpoint_creates_drafts_and_dispatches(): void
    {
        Queue::fake();
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage');

        $this->actingAs($manager)
            ->post('/atelier/patterns/import', [
                'files' => [
                    UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Pattern::where('extraction_status', Pattern::EXTRACTION_PROCESSING)->count());
        Queue::assertPushed(ExtractPatternFromPdfJob::class, 2);
    }

    public function test_retry_requeues_failed_extraction(): void
    {
        Queue::fake();
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage');

        $pattern = Pattern::create([
            'name' => 'k', 'product_type' => 'belirsiz', 'status' => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_FAILED, 'extraction_error' => 'servis kapalı',
            'pdf_path' => 'atelier/patterns/x.pdf',
        ]);

        $this->actingAs($manager)
            ->post("/atelier/patterns/{$pattern->id}/retry-extraction")
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(Pattern::EXTRACTION_PROCESSING, $pattern->fresh()->extraction_status);
        $this->assertNull($pattern->fresh()->extraction_error);
        Queue::assertPushed(ExtractPatternFromPdfJob::class, 1);
    }

    public function test_retry_rejected_when_not_failed(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->givePermissionTo('atelier.pattern.manage');

        $pattern = Pattern::create([
            'name' => 'k', 'product_type' => 'tulum', 'status' => Pattern::STATUS_APPROVED,
            'pdf_path' => 'atelier/patterns/x.pdf', // extraction_status null
        ]);

        $this->actingAs($manager)
            ->post("/atelier/patterns/{$pattern->id}/retry-extraction")
            ->assertSessionHasErrors('extraction');
    }

    private function manager(): User
    {
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage');

        return $u;
    }

    /** @param array<string,mixed>|null $probe */
    private function bindProbe(?array $probe): void
    {
        $this->app->bind(PdfDxfConverterContract::class, fn () => new class($probe) implements PdfDxfConverterContract {
            public function __construct(private ?array $probe) {}

            public function convert(string $pdfAbsolutePath): ConversionResult
            {
                return new ConversionResult('green', 90, "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n", ['product_type' => 'tulum'], []);
            }

            public function probe(string $pdfAbsolutePath): ?array
            {
                return $this->probe;
            }
        });
    }

    public function test_raster_pdf_rejected_on_import(): void
    {
        Queue::fake();
        $this->bindProbe(['kind' => 'raster', 'pages' => 42, 'image_pages' => 38]);

        $this->actingAs($this->manager())
            ->post('/atelier/patterns/import', ['files' => [UploadedFile::fake()->create('salopeta.pdf', 100, 'application/pdf')]])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Pattern::count());
        Queue::assertNothingPushed();
    }

    public function test_vector_pdf_passes_precheck(): void
    {
        Queue::fake();
        $this->bindProbe(['kind' => 'vector_tiled', 'pages' => 26, 'has_grid' => true]);

        $this->actingAs($this->manager())
            ->post('/atelier/patterns/import', ['files' => [UploadedFile::fake()->create('kombinezon.pdf', 100, 'application/pdf')]])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Pattern::where('extraction_status', Pattern::EXTRACTION_PROCESSING)->count());
        Queue::assertPushed(ExtractPatternFromPdfJob::class, 1);
    }

    public function test_null_probe_proceeds_service_down_fallback(): void
    {
        Queue::fake();
        $this->bindProbe(null); // servis kapalı → ön-kontrol atlanır, akış sürer

        $this->actingAs($this->manager())
            ->post('/atelier/patterns/import', ['files' => [UploadedFile::fake()->create('x.pdf', 100, 'application/pdf')]])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Pattern::count());
        Queue::assertPushed(ExtractPatternFromPdfJob::class, 1);
    }

    public function test_view_permission_cannot_import(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.pattern.view');

        $this->actingAs($viewer)
            ->post('/atelier/patterns/import', ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])
            ->assertForbidden();
    }
}
