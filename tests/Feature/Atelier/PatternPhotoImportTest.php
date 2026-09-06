<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Jobs\ExtractPatternFromPhotoJob;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Ai\Contracts\PatternMetadataExtractorContract;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\ConversionResult;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Modules\Atelier\Services\PatternLibraryService;
use Modules\Atelier\Services\PatternMetadataRuleEngine;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternPhotoImportTest extends TestCase
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

    private function manager(): User
    {
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage');

        return $u;
    }

    public function test_create_photo_draft_makes_processing_draft(): void
    {
        $pattern = $this->library()->createPhotoDraft(
            UploadedFile::fake()->image('kapusonlu_tulum.jpg')
        );

        $this->assertSame(Pattern::STATUS_DRAFT, $pattern->status);
        $this->assertSame(Pattern::EXTRACTION_PROCESSING, $pattern->extraction_status);
        $this->assertSame('kapusonlu_tulum', $pattern->name);
        Storage::disk('public')->assertExists($pattern->photo_path);
    }

    public function test_marker_missing_result_marks_failed_with_actionable_error(): void
    {
        // Fotoğrafta ArUco marker bulunamadı senaryosu — converter 'red' + açıklayıcı hata döner.
        $this->app->bind(PdfDxfConverterContract::class, fn () => new class implements PdfDxfConverterContract {
            public function convert(string $pdfAbsolutePath): ConversionResult
            {
                return new ConversionResult('red', 0);
            }

            public function convertPhoto(string $photoAbsolutePath, float $markerMm = 40.0): ConversionResult
            {
                return new ConversionResult(
                    classification: 'red', confidence: 0, dxf: null,
                    metadata: ['name' => 'x', 'source' => 'photo'],
                    errors: ['Referans marker (ArUco) bulunamadı — yeniden çekin.'],
                );
            }

            public function probe(string $pdfAbsolutePath): ?array
            {
                return null;
            }

            public function renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string
            {
                return '';
            }

            public function buildDxf(array $polylines): string
            {
                return '';
            }
        });

        $pattern = $this->library()->createPhotoDraft(UploadedFile::fake()->image('markersiz_foto.jpg'));

        (new ExtractPatternFromPhotoJob($pattern->id))
            ->handle(
                app(PdfDxfConverterContract::class),
                $this->library(),
                app(PatternMetadataExtractorContract::class),
                app(PatternMetadataRuleEngine::class),
            );

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_FAILED, $pattern->extraction_status);
        $this->assertStringContainsString('marker', $pattern->extraction_error);
    }

    public function test_job_handle_completes_draft_with_scale_verified(): void
    {
        $pattern = $this->library()->createPhotoDraft(UploadedFile::fake()->image('kalip_foto.jpg'));

        (new ExtractPatternFromPhotoJob($pattern->id))
            ->handle(
                app(PdfDxfConverterContract::class),
                $this->library(),
                app(PatternMetadataExtractorContract::class),
                app(PatternMetadataRuleEngine::class),
            );

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_DONE, $pattern->extraction_status);
        $this->assertTrue((bool) $pattern->scale_verified);
        $this->assertNotNull($pattern->dxf_path);
    }

    public function test_apply_extraction_photo_source_red_fails_not_needs_tracing(): void
    {
        // 'raster' PDF kaynağının aksine fotoğrafta marker yoksa ölçek yok — needs_tracing
        // anlamsız (izlense bile mm'ye çevrilemez); doğrudan 'failed' + yeniden çekim talimatı.
        $pattern = $this->library()->createPhotoDraft(UploadedFile::fake()->image('x.jpg'));

        $result = new ConversionResult(
            classification: 'red', confidence: 0, dxf: null,
            metadata: ['name' => 'x', 'source' => 'photo'],
            errors: ['Referans marker bulunamadı.'],
        );

        $this->library()->applyExtraction($pattern, $result);

        $pattern->refresh();
        $this->assertSame(Pattern::EXTRACTION_FAILED, $pattern->extraction_status);
        $this->assertNotSame(Pattern::EXTRACTION_NEEDS_TRACING, $pattern->extraction_status);
    }

    public function test_import_endpoint_creates_drafts_and_dispatches(): void
    {
        Queue::fake();

        $this->actingAs($this->manager())
            ->post('/atelier/patterns/import-photo', [
                'files' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.png'),
                ],
                'marker_mm' => 40,
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Pattern::where('extraction_status', Pattern::EXTRACTION_PROCESSING)->count());
        Queue::assertPushed(ExtractPatternFromPhotoJob::class, 2);
    }

    public function test_view_permission_cannot_import_photo(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.pattern.view');

        $this->actingAs($viewer)
            ->post('/atelier/patterns/import-photo', ['files' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertForbidden();
    }

    public function test_non_image_file_rejected(): void
    {
        $this->actingAs($this->manager())
            ->post('/atelier/patterns/import-photo', [
                'files' => [UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasErrors('files.0');
    }
}
