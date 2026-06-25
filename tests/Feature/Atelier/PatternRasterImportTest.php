<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_raster_pdf_becomes_needs_tracing_draft(): void
    {
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
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
        ]);
    }
}
