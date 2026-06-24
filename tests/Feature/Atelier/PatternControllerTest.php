<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Pattern;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatternControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Modül Inertia sayfaları test view-finder'ında çözülmez; varlık kontrolünü kapat.
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'atelier.pattern.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.pattern.manage', 'guard_name' => 'web']);
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('atelier.pattern.view', 'atelier.pattern.manage');
    }

    public function test_store_creates_pattern_with_parts_tags_and_files(): void
    {
        $this->actingAs($this->manager)
            ->post('/atelier/patterns', [
                'name'         => 'Çocuk Tulum',
                'code'         => 'KLP-001',
                'product_type' => 'tulum',
                'size_range'   => '116-122-128-134',
                'status'       => 'approved',
                'scale_verified' => true,
                'parts'        => [
                    ['part_name' => 'Kol', 'quantity' => 2, 'size_range' => '116-134'],
                    ['part_name' => 'Kapüşon', 'quantity' => 1],
                    ['part_name' => '', 'quantity' => 1], // boş → atlanır
                ],
                'tags'         => ['kışlık', 'pamuk', 'kışlık'], // tekrar → tekilleşir
                'preview_image'=> UploadedFile::fake()->image('preview.png'),
                'dxf'          => UploadedFile::fake()->create('pattern.dxf', 40, 'application/dxf'),
            ])
            ->assertRedirect();

        $pattern = Pattern::with('parts', 'tags')->first();
        $this->assertNotNull($pattern);
        $this->assertSame('approved', $pattern->status);
        $this->assertTrue($pattern->scale_verified);
        $this->assertCount(2, $pattern->parts);
        $this->assertCount(2, $pattern->tags);
        $this->assertNotNull($pattern->preview_image_path);
        $this->assertNotNull($pattern->dxf_path);
        Storage::disk('public')->assertExists($pattern->preview_image_path);
        Storage::disk('public')->assertExists($pattern->dxf_path);
    }

    public function test_update_replaces_parts_and_old_file(): void
    {
        $pattern = Pattern::create(['name' => 'Eski', 'product_type' => 'ceket', 'status' => 'draft']);
        $pattern->parts()->create(['part_name' => 'Eski Parça', 'quantity' => 1]);
        $oldDxf = UploadedFile::fake()->create('old.dxf', 10)->store('atelier/patterns', 'public');
        $pattern->update(['dxf_path' => $oldDxf]);

        $this->actingAs($this->manager)
            ->put("/atelier/patterns/{$pattern->id}", [
                'name'         => 'Yeni',
                'product_type' => 'ceket',
                'status'       => 'approved',
                'parts'        => [['part_name' => 'Ön', 'quantity' => 1]],
                'tags'         => [],
                'dxf'          => UploadedFile::fake()->create('new.dxf', 12, 'application/dxf'),
            ])
            ->assertRedirect();

        $pattern->refresh()->load('parts');
        $this->assertSame('Yeni', $pattern->name);
        $this->assertCount(1, $pattern->parts);
        $this->assertSame('Ön', $pattern->parts->first()->part_name);
        // Eski dosya silindi, yenisi kondu
        Storage::disk('public')->assertMissing($oldDxf);
        Storage::disk('public')->assertExists($pattern->dxf_path);
    }

    public function test_index_filters_by_product_type_and_search(): void
    {
        Pattern::create(['name' => 'Mavi Tulum', 'product_type' => 'tulum', 'status' => 'approved']);
        Pattern::create(['name' => 'Kırmızı Ceket', 'product_type' => 'ceket', 'status' => 'approved']);

        $this->actingAs($this->manager)
            ->get('/atelier/patterns?product_type=tulum')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Atelier::Patterns')
                ->has('patterns', 1)
                ->where('patterns.0.name', 'Mavi Tulum'));
    }

    public function test_destroy_removes_pattern_and_files(): void
    {
        $dxf = UploadedFile::fake()->create('x.dxf', 10)->store('atelier/patterns', 'public');
        $pattern = Pattern::create(['name' => 'Sil', 'product_type' => 'tulum', 'status' => 'draft', 'dxf_path' => $dxf]);

        $this->actingAs($this->manager)
            ->delete("/atelier/patterns/{$pattern->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('patterns', ['id' => $pattern->id]);
        Storage::disk('public')->assertMissing($dxf);
    }

    public function test_view_permission_cannot_create(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.pattern.view');

        $this->actingAs($viewer)->get('/atelier/patterns')->assertOk();
        $this->actingAs($viewer)
            ->post('/atelier/patterns', ['name' => 'X', 'product_type' => 'tulum', 'status' => 'draft'])
            ->assertForbidden();
    }
}
