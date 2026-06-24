<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Pattern;
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
            'measurements'   => ['labels' => ['OW', 'TW'], 'matrix' => ['OW' => [22.5]]],
            'fabric_usage'   => ['50/56' => '30cm'],
        ]);

        $fresh = $pattern->fresh();
        $this->assertSame(['BEDEN_YESIL', 'BEDEN_CYAN'], $fresh->size_layers);
        $this->assertSame(['OW', 'TW'], $fresh->measurements['labels']);
        $this->assertSame(['50/56' => '30cm'], $fresh->fabric_usage);
    }
}
