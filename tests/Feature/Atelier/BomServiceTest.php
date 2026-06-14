<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Services\BomService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Tests\TestCase;

class BomServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Tişört', 'slug' => 'tisort-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);

        return Product::create([
            'category_id' => $cat->id, 'name' => 'Bebek Tişört',
            'sku' => 'SKU-' . uniqid(), 'gender' => 'Unisex', 'price' => 50,
        ]);
    }

    public function test_requirements_compute_quantity_and_cost_with_waste(): void
    {
        $product = $this->product();
        $kumas = Material::create(['code' => 'K1', 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10]);
        $dugme = Material::create(['code' => 'D1', 'name' => 'Düğme', 'type' => 'aksesuar', 'unit' => 'adet', 'unit_cost' => 0.5]);

        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 10]); // 1m + %10 fire
        $bom->lines()->create(['material_id' => $dugme->id, 'quantity_per_unit' => 4, 'waste_pct' => 0]);

        $req = app(BomService::class)->requirementsFor($product, 100);

        // kumaş: 100 * 1.0 * 1.10 = 110 metre, maliyet 110 * 10 = 1100
        $kumasReq = collect($req)->firstWhere('material_id', $kumas->id);
        $this->assertEqualsWithDelta(110.0, $kumasReq['required_qty'], 0.001);
        $this->assertEqualsWithDelta(1100.0, $kumasReq['line_cost'], 0.001);

        // düğme: 100 * 4 = 400 adet, maliyet 400 * 0.5 = 200
        $dugmeReq = collect($req)->firstWhere('material_id', $dugme->id);
        $this->assertEqualsWithDelta(400.0, $dugmeReq['required_qty'], 0.001);
        $this->assertEqualsWithDelta(200.0, $dugmeReq['line_cost'], 0.001);
    }

    public function test_returns_empty_when_no_active_bom(): void
    {
        $product = $this->product();

        $this->assertSame([], app(BomService::class)->requirementsFor($product, 10));
    }
}
