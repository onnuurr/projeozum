<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Services\MaterialSpecService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MaterialSpecsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.material.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('atelier.view', 'atelier.material.manage');
        $this->material = Material::create([
            'code' => 'KM-100', 'name' => 'Pamuklu Süprem',
            'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10,
        ]);
    }

    public function test_update_specs_saves_allowed_keys(): void
    {
        $this->actingAs($this->admin)
            ->put("/atelier/materials/{$this->material->id}/specs", [
                'specs' => [
                    'composition' => '%60 pamuk %40 polyester',
                    'gsm'         => 180,
                    'weave'       => 'jarse',
                    'care_instructions' => '40°C yıkanır.',
                ],
            ])
            ->assertRedirect();

        $fresh = $this->material->fresh();
        $this->assertSame('%60 pamuk %40 polyester', $fresh->specs['composition']);
        $this->assertSame(180, $fresh->specs['gsm']);
        $this->assertSame('jarse', $fresh->specs['weave']);
    }

    public function test_update_specs_rejects_unknown_keys_via_validation(): void
    {
        $this->actingAs($this->admin)
            ->put("/atelier/materials/{$this->material->id}/specs", [
                'specs' => ['unknown_key' => 'x'],
            ])
            ->assertRedirect();

        // Allowlist filtreleyeceği için unknown_key JSON'a girmez.
        $this->assertNull($this->material->fresh()->specs);
    }

    public function test_service_rejects_unknown_keys_directly(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MaterialSpecService::class)->updateSpecs($this->material, ['foo' => 'bar']);
    }

    public function test_format_for_prompt_joins_readable_string(): void
    {
        $this->material->specs = [
            'composition' => '%100 pamuk',
            'gsm'         => 200,
            'weave'       => 'ribana',
        ];
        $this->material->save();

        $out = app(MaterialSpecService::class)->formatForPrompt($this->material->fresh());
        $this->assertStringContainsString('Pamuklu Süprem', $out);
        $this->assertStringContainsString('%100 pamuk', $out);
        $this->assertStringContainsString('200 gsm', $out);
        $this->assertStringContainsString('ribana', $out);
    }

    public function test_requires_manage_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.view');

        $this->actingAs($viewer)
            ->put("/atelier/materials/{$this->material->id}/specs", ['specs' => []])
            ->assertForbidden();
    }
}
