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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternRasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
    }

    public function test_raster_pdf_dispatches_auto_vectorization_job(): void
    {
        // Raster artık import'ta atlanmaz/needs_tracing yapılmaz: otomatik vektörleştirme
        // job'ı kuyruğa atılır (taslak 'processing'). needs_tracing yalnızca job red dönerse olur.
        Queue::fake();
        $user = User::factory()->create();
        $user->givePermissionTo('atelier.pattern.manage');

        $this->mock(PdfDxfConverterContract::class, function ($m) {
            $m->shouldReceive('probe')->andReturn(['kind' => 'raster']);
        });

        $this->actingAs($user)
            ->post('/atelier/patterns/import', [
                'files' => [UploadedFile::fake()->create('shik.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patterns', [
            'extraction_status' => Pattern::EXTRACTION_PROCESSING,
        ]);
        Queue::assertPushed(ExtractPatternFromPdfJob::class);
    }
}
