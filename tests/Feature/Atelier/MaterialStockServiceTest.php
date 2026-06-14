<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Services\MaterialStockService;
use Tests\TestCase;

class MaterialStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MaterialStockService
    {
        return app(MaterialStockService::class);
    }

    private function material(float $stock = 0): Material
    {
        return Material::create([
            'code' => 'M-' . uniqid(), 'name' => 'Pamuk Kumaş',
            'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10,
            'current_stock' => $stock,
        ]);
    }

    public function test_in_movement_increases_stock_and_records_movement(): void
    {
        $material = $this->material(5);

        $movement = $this->service()->record($material, MaterialMovement::TYPE_IN, 10, 'purchase');

        $this->assertSame('15.000', $material->fresh()->current_stock);
        $this->assertSame('5.000', $movement->before_stock);
        $this->assertSame('15.000', $movement->after_stock);
        $this->assertSame('10.000', $movement->quantity);
    }

    public function test_out_movement_decreases_stock_with_negative_quantity(): void
    {
        $material = $this->material(20);

        $movement = $this->service()->record($material, MaterialMovement::TYPE_OUT, 8, 'consume');

        $this->assertSame('12.000', $material->fresh()->current_stock);
        $this->assertSame('-8.000', $movement->quantity);
    }

    public function test_out_movement_below_zero_throws(): void
    {
        $material = $this->material(3);

        $this->expectExceptionMessage('Hammadde stoğu negatife düşemez');

        $this->service()->record($material, MaterialMovement::TYPE_OUT, 5, 'consume');
    }
}
