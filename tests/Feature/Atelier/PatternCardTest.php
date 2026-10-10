<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Models\PatternMeasurement;
use Modules\PatternEngine\Models\PatternMeasurementPoint;
use Modules\PatternEngine\Models\PatternType;
use Modules\PatternEngine\Services\Conversion\ConversionResult;
use Modules\PatternEngine\Services\PatternLibraryService;
use Modules\PatternEngine\Services\PatternMeasurementService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Kalıp Kartı (ölçü tabloları, parçalar, sarfiyat) + Kalıp Ayarları (tipler, ölçü noktaları). */
class PatternCardTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
        foreach (['atelier.pattern.view', 'atelier.pattern.manage', 'atelier.pattern.settings'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage', 'atelier.pattern.settings');
        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('atelier.pattern.view');
    }

    private function pattern(array $attrs = []): Pattern
    {
        $type = PatternType::where('name', 'sweatshirt')->firstOrFail(); // migration seed'i

        return Pattern::create(array_merge([
            'name' => 'Tolstovka', 'product_type' => 'sweatshirt', 'pattern_type_id' => $type->id,
            'status' => Pattern::STATUS_DRAFT, 'sizes' => ['122', '128'],
        ], $attrs));
    }

    private function pointId(string $key): int
    {
        return PatternMeasurementPoint::where('key', $key)->value('id');
    }

    public function test_card_shows_template_rows_for_both_bases(): void
    {
        $p = $this->pattern();

        $this->actingAs($this->viewer)->get("/atelier/patterns/{$p->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PatternEngine::PatternCard')
                ->where('pattern.sizes', ['122', '128'])
                ->where('measurementTables.0.basis', 'body')
                ->where('measurementTables.1.basis', 'garment')
                ->has('measurementTables.0.rows', 6)
                ->where('canManage', false));
    }

    public function test_viewer_cannot_save_measurements(): void
    {
        $p = $this->pattern();

        $this->actingAs($this->viewer)
            ->put("/atelier/patterns/{$p->id}/measurements", ['tables' => []])
            ->assertForbidden();
    }

    public function test_save_measurements_keeps_unchanged_pdf_cells_as_pdf(): void
    {
        $p = $this->pattern();
        $chest = $this->pointId('chest_circ');
        $p->measurements()->create(['measurement_point_id' => $chest, 'basis' => 'body', 'size_label' => '122', 'value' => 64, 'source' => 'pdf']);
        $p->measurements()->create(['measurement_point_id' => $chest, 'basis' => 'body', 'size_label' => '128', 'value' => 66, 'source' => 'pdf']);

        $this->actingAs($this->manager)->put("/atelier/patterns/{$p->id}/measurements", [
            'tables' => [
                ['basis' => 'body', 'group_label' => null, 'rows' => [
                    ['point_id' => $chest, 'values' => ['122' => 64, '128' => 67]],
                ]],
                ['basis' => 'garment', 'group_label' => null, 'rows' => [
                    ['point_id' => null, 'custom_label' => 'Cep eni', 'values' => ['122' => 14.5]],
                ]],
            ],
        ])->assertRedirect();

        $rows = $p->measurements()->get()->keyBy(fn ($m) => ($m->custom_label ?? $m->measurement_point_id) . '@' . $m->size_label);
        $this->assertSame('pdf', $rows["{$chest}@122"]->source);
        $this->assertSame('manual', $rows["{$chest}@128"]->source);
        $this->assertSame(67.0, $rows["{$chest}@128"]->value);
        $this->assertSame(14.5, $rows['Cep eni@122']->value);
    }

    public function test_pdf_import_replaces_pdf_cells_but_never_manual_ones(): void
    {
        $p = $this->pattern();
        $chest = $this->pointId('chest_circ');
        $p->measurements()->create(['measurement_point_id' => $chest, 'basis' => 'body', 'size_label' => '122', 'value' => 99, 'source' => 'manual']);
        $p->measurements()->create(['measurement_point_id' => $chest, 'basis' => 'body', 'size_label' => '134', 'value' => 1, 'source' => 'pdf']);

        app(PatternMeasurementService::class)->importFromPdf($p, [
            'body_measurements' => [
                ['point_key' => 'chest_circ', 'values' => ['122' => 64, '128' => 66]],
                ['point_key' => 'unknown_key', 'label' => 'Bilinmeyen', 'values' => ['122' => 5]],
            ],
        ]);

        $vals = $p->measurements()->get();
        $this->assertSame(99.0, $vals->where('measurement_point_id', $chest)->firstWhere('size_label', '122')->value);
        $this->assertNull($vals->where('measurement_point_id', $chest)->firstWhere('size_label', '134'));
        $this->assertSame(66.0, $vals->where('measurement_point_id', $chest)->firstWhere('size_label', '128')->value);
        $this->assertSame('Bilinmeyen', $vals->firstWhere('custom_label', 'Bilinmeyen')->custom_label);
    }

    public function test_legacy_nipnaps_matrix_maps_to_points(): void
    {
        $p = $this->pattern();

        app(PatternMeasurementService::class)->importFromPdf($p, [
            'measurements' => ['labels' => ['OW'], 'sizes' => ['122', '128'], 'matrix' => ['OW' => [62.0, 64.0]], 'unit' => 'cm'],
        ]);

        $this->assertSame(2, $p->measurements()->where('measurement_point_id', $this->pointId('chest_circ'))->count());
    }

    public function test_save_production_stores_card_data_and_syncs_product_type(): void
    {
        $p = $this->pattern();
        $pants = PatternType::where('name', 'pantolon')->firstOrFail();

        $this->actingAs($this->manager)->put("/atelier/patterns/{$p->id}/production", [
            'pattern_type_id' => $pants->id,
            'sizes' => ['122', '128', '134'],
            'variants' => [['code' => 'A', 'name' => 'Anorak']],
            'seam_allowance_cm' => 1.5,
            'parts' => [
                ['part_no' => '6', 'part_name' => 'Kol', 'quantity' => 2, 'variants' => 'A,B', 'fabric_code' => 'I'],
                ['part_name' => ''],
            ],
            'fabric_usage' => [
                'fabrics' => [['code' => 'I', 'name' => 'Sweat']],
                'rows' => [['variant' => 'A', 'fabric' => 'I', 'width_cm' => 140, 'values' => ['122' => 0.95]]],
            ],
            'materials' => [['name' => 'Kuşgözü', 'quantity' => 2, 'unit' => 'adet']],
        ])->assertRedirect();

        $fresh = $p->fresh('parts');
        $this->assertSame('pantolon', $fresh->product_type);
        $this->assertSame(['122', '128', '134'], $fresh->sizes);
        $this->assertSame(1.5, (float) $fresh->seam_allowance_cm);
        $this->assertSame(0.95, $fresh->fabric_usage['rows'][0]['values']['122']);
        $this->assertSame('m', $fresh->fabric_usage['unit']);
        $this->assertCount(1, $fresh->parts);
        $this->assertSame('6', $fresh->parts[0]->part_no);
        $this->assertSame('I', $fresh->parts[0]->fabric_code);
        $this->assertSame('Kuşgözü', $fresh->materials[0]['name']);
    }

    public function test_apply_extraction_keeps_name_and_size_range_and_imports_production_data(): void
    {
        $p = $this->pattern(['name' => 'Tolstovka_det_r_122-164', 'size_range' => '122-164', 'pdf_path' => 'atelier/patterns/x.pdf']);
        $p->parts()->create(['part_name' => 'Elle girilen', 'quantity' => 1]);

        app(PatternLibraryService::class)->applyExtraction($p, new ConversionResult('green', 90.0, "0\nEOF\n", [
            'name' => 'xGvljv64hash', 'profile' => 'burda', 'product_type' => 'kapüşonlu', 'size_range' => null,
            'sizes' => ['122', '128'], 'seam_allowance_cm' => 1.5,
            'body_measurements' => [['point_key' => 'height', 'values' => ['122' => 122, '128' => 128]]],
            'parts' => [],
        ], []));

        $fresh = $p->fresh('parts');
        $this->assertSame('Tolstovka_det_r_122-164', $fresh->name);
        $this->assertSame('122-164', $fresh->size_range);
        $this->assertSame('kapüşonlu', $fresh->type->name);
        $this->assertSame(1.5, (float) $fresh->seam_allowance_cm);
        $this->assertSame('Elle girilen', $fresh->parts[0]->part_name); // PDF parça vermedi → korunur
        $this->assertSame(2, $fresh->measurements()->where('source', PatternMeasurement::SOURCE_PDF)->count());
    }

    public function test_settings_requires_settings_permission(): void
    {
        $this->actingAs($this->viewer)->get('/atelier/patterns/settings')->assertForbidden();
        $this->actingAs($this->viewer)->post('/atelier/patterns/settings/types', ['name' => 'x'])->assertForbidden();
        $this->actingAs($this->manager)->get('/atelier/patterns/settings')->assertOk();
    }

    public function test_type_template_is_saved_in_given_order_and_in_use_type_is_only_deactivated(): void
    {
        $ids = [$this->pointId('g_length'), $this->pointId('height')];

        $this->actingAs($this->manager)->post('/atelier/patterns/settings/types', [
            'name' => 'yelek', 'is_active' => true, 'point_ids' => $ids,
        ])->assertRedirect();

        $type = PatternType::where('name', 'yelek')->firstOrFail();
        $this->assertSame($ids, $type->measurementPoints->pluck('id')->all());

        $p = $this->pattern(['pattern_type_id' => $type->id]);
        $this->actingAs($this->manager)->delete("/atelier/patterns/settings/types/{$type->id}")->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);
        $this->assertSame($type->id, $p->fresh()->pattern_type_id);
    }

    public function test_point_key_must_be_snake_case_and_unique(): void
    {
        $this->actingAs($this->manager)->post('/atelier/patterns/settings/points', [
            'key' => 'Göğüs Eni', 'name' => 'x', 'basis' => 'garment',
        ])->assertSessionHasErrors('key');

        $this->actingAs($this->manager)->post('/atelier/patterns/settings/points', [
            'key' => 'height', 'name' => 'x', 'basis' => 'body',
        ])->assertSessionHasErrors('key');
    }
}
