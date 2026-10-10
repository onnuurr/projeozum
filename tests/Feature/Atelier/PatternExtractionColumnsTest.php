<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PatternEngine\Models\Pattern;
use Tests\TestCase;

class PatternExtractionColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extraction_json_columns_as_arrays(): void
    {
        $pattern = Pattern::create([
            'name'           => 'Latzee',
            'product_type'   => 'tulum',
            'size_layers'    => ['BEDEN_YESIL', 'BEDEN_CYAN'],
            'color_size_map' => ['#00FF00' => 'yesil'],
            'sizes'          => ['50/56', '62/68'],
            'variants'       => [['code' => 'A', 'name' => 'Anorak']],
            'materials'      => [['name' => 'Kuşgözü', 'quantity' => 2]],
            'fabric_usage'   => ['50/56' => '30cm'],
        ]);

        $fresh = $pattern->fresh();
        $this->assertSame(['BEDEN_YESIL', 'BEDEN_CYAN'], $fresh->size_layers);
        // measurements JSON kolonu 2026-10-07'de pattern_measurements tablosuna taşındı.
        $this->assertSame(['50/56', '62/68'], $fresh->sizes);
        $this->assertSame('A', $fresh->variants[0]['code']);
        $this->assertSame('Kuşgözü', $fresh->materials[0]['name']);
        $this->assertSame(['50/56' => '30cm'], $fresh->fabric_usage);
    }
}
