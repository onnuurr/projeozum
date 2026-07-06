<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\BomLine;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductDescriptionMaterial;
use Modules\Product\Services\ProductMaterialLinkService;
use Tests\TestCase;

class ProductMaterialLinkTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Material $fabric;
    private Material $trim;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Basic Tee',
            'sku' => 'BT-' . uniqid(), 'gender' => 'Unisex', 'price' => 100,
        ]);
        $this->fabric = Material::create([
            'code' => 'F-01', 'name' => 'Pamuklu Süprem',
            'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 30,
        ]);
        $this->trim = Material::create([
            'code' => 'T-01', 'name' => 'Etiket',
            'type' => 'etiket', 'unit' => 'adet', 'unit_cost' => 1,
        ]);
    }

    public function test_sync_materials_upserts_and_deletes_stale(): void
    {
        $service = app(ProductMaterialLinkService::class);

        $service->syncMaterials($this->product, [
            ['material_id' => $this->fabric->id, 'role' => 'primary_fabric', 'sort_order' => 0],
            ['material_id' => $this->trim->id,   'role' => 'trim',            'sort_order' => 1],
        ]);

        $this->assertDatabaseCount('product_description_materials', 2);

        // Aynı üründe trim'i çıkar → sadece fabric kalmalı.
        $service->syncMaterials($this->product, [
            ['material_id' => $this->fabric->id, 'role' => 'primary_fabric', 'sort_order' => 0],
        ]);
        $this->assertDatabaseCount('product_description_materials', 1);
        $this->assertDatabaseHas('product_description_materials', [
            'product_id'  => $this->product->id,
            'material_id' => $this->fabric->id,
            'role'        => 'primary_fabric',
        ]);
    }

    public function test_resolve_prefers_pivot_over_bom(): void
    {
        $bom = ProductBom::create([
            'product_id' => $this->product->id, 'name' => 'v1', 'is_active' => true,
        ]);
        BomLine::create([
            'bom_id' => $bom->id, 'material_id' => $this->fabric->id,
            'quantity_per_unit' => 1.2, 'waste_pct' => 5,
        ]);

        ProductDescriptionMaterial::create([
            'product_id' => $this->product->id, 'material_id' => $this->trim->id,
            'role' => 'trim', 'sort_order' => 0,
        ]);

        $rows = app(ProductMaterialLinkService::class)->resolve($this->product);
        $this->assertCount(1, $rows);
        $this->assertSame($this->trim->id, $rows->first()['material']->id);
        $this->assertSame('trim', $rows->first()['role']);
    }

    public function test_resolve_falls_back_to_bom_when_no_pivot(): void
    {
        $bom = ProductBom::create([
            'product_id' => $this->product->id, 'name' => 'v1', 'is_active' => true,
        ]);
        BomLine::create([
            'bom_id' => $bom->id, 'material_id' => $this->fabric->id,
            'quantity_per_unit' => 1.2, 'waste_pct' => 5,
        ]);

        $rows = app(ProductMaterialLinkService::class)->resolve($this->product);
        $this->assertCount(1, $rows);
        $this->assertSame($this->fabric->id, $rows->first()['material']->id);
        $this->assertSame('primary_fabric', $rows->first()['role']);
    }

    public function test_resolve_returns_empty_when_no_pivot_and_no_bom(): void
    {
        $rows = app(ProductMaterialLinkService::class)->resolve($this->product);
        $this->assertTrue($rows->isEmpty());
    }
}
