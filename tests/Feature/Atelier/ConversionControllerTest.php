<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Jobs\ProcessConversionJob;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConversionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['atelier.conversion.disk' => 'public']);
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->app->bind(PdfDxfConverterContract::class, MockPdfDxfConverter::class);

        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.conversion.manage', 'guard_name' => 'web']);
        $this->operator = User::factory()->create();
        $this->operator->givePermissionTo('atelier.view', 'atelier.conversion.manage');
    }

    public function test_bulk_upload_creates_jobs_and_dispatches(): void
    {
        Queue::fake();

        $this->actingAs($this->operator)
            ->post('/atelier/conversions', [
                'files' => [
                    UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, ConversionJob::where('status', ConversionJob::STATUS_PENDING)->count());
        Queue::assertPushed(ProcessConversionJob::class, 2);
    }

    public function test_approve_creates_pattern(): void
    {
        // İşlenmiş, incelemede bir iş hazırla.
        $job = ConversionJob::create([
            'source_pdf_path' => 'atelier/conversions/pdf/x.pdf',
            'output_dxf_path' => 'atelier/conversions/dxf/x.dxf',
            'classification'  => ConversionJob::CLASS_GREEN,
            'confidence_score'=> 94,
            'status'          => ConversionJob::STATUS_NEEDS_REVIEW,
            'error_report'    => ['metadata' => ['product_type' => 'ceket', 'parts' => [['part_name' => 'Ön', 'quantity' => 1]]], 'errors' => []],
        ]);

        $this->actingAs($this->operator)
            ->post("/atelier/conversions/{$job->id}/approve")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pattern = Pattern::first();
        $this->assertNotNull($pattern);
        $this->assertSame('ceket', $pattern->product_type);
        $this->assertSame($pattern->id, $job->fresh()->pattern_id);
    }

    public function test_approve_blocked_without_dxf(): void
    {
        $job = ConversionJob::create([
            'source_pdf_path' => 'atelier/conversions/pdf/x.pdf',
            'classification'  => ConversionJob::CLASS_RED,
            'status'          => ConversionJob::STATUS_FAILED,
            'error_report'    => ['errors' => ['Izgara okunamadı']],
        ]);

        $this->actingAs($this->operator)
            ->post("/atelier/conversions/{$job->id}/approve")
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Pattern::count());
    }

    public function test_reject_closes_job(): void
    {
        $job = ConversionJob::create([
            'source_pdf_path' => 'atelier/conversions/pdf/x.pdf',
            'status'          => ConversionJob::STATUS_NEEDS_REVIEW,
        ]);

        $this->actingAs($this->operator)
            ->post("/atelier/conversions/{$job->id}/reject")
            ->assertRedirect();

        $this->assertSame(ConversionJob::STATUS_REJECTED, $job->fresh()->status);
    }

    public function test_view_only_cannot_upload(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.view');

        $this->actingAs($viewer)->get('/atelier/conversions')->assertOk();
        $this->actingAs($viewer)
            ->post('/atelier/conversions', ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])
            ->assertForbidden();
    }
}
