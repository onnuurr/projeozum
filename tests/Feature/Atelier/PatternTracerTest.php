<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternTracerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('atelier.pattern.manage');
        $this->app->bind(PdfDxfConverterContract::class, MockPdfDxfConverter::class);
    }

    private function rasterPattern(): Pattern
    {
        Storage::disk('public')->put('atelier/patterns/r.pdf', '%PDF-1.4');

        return Pattern::create([
            'name' => 'R', 'product_type' => 'belirsiz', 'status' => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING, 'pdf_path' => 'atelier/patterns/r.pdf',
        ]);
    }

    public function test_tracer_requires_permission(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs(User::factory()->create())
            ->get("/atelier/patterns/{$p->id}/tracer")
            ->assertForbidden();
    }

    public function test_tracer_image_returns_png(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->get("/atelier/patterns/{$p->id}/tracer-image?page=0")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_save_traced_marks_done_with_dxf(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->post("/atelier/patterns/{$p->id}/traced", [
                'name' => 'Ceket', 'product_type' => 'ceket', 'size_range' => '38-44',
                'calibration' => ['px_per_mm' => 3.78, 'image_height_px' => 1200],
                'pieces' => [[
                    'name' => 'Ön', 'quantity' => 1, 'size' => '38',
                    'polylines' => [['role' => 'cut', 'points' => [[0, 0], [100, 0], [100, 100]]]],
                ]],
            ])
            ->assertRedirect(route('atelier.patterns.index'));

        $fresh = $p->fresh('parts');
        $this->assertSame(Pattern::EXTRACTION_DONE, $fresh->extraction_status);
        $this->assertNotNull($fresh->dxf_path);
        $this->assertSame(1, $fresh->parts->count());
    }

    public function test_save_traced_validation_fails_without_calibration(): void
    {
        $p = $this->rasterPattern();
        $this->actingAs($this->admin)
            ->post("/atelier/patterns/{$p->id}/traced", [
                'name' => 'Ceket',
                'pieces' => [['name' => 'Ön', 'polylines' => [['role' => 'cut', 'points' => [[0, 0], [1, 0], [1, 1]]]]]],
            ])
            ->assertSessionHasErrors('calibration.px_per_mm');
    }
}
