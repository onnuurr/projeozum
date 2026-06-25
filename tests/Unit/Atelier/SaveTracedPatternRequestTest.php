<?php

namespace Tests\Unit\Atelier;

use Illuminate\Support\Facades\Validator;
use Modules\Atelier\Http\Requests\SaveTracedPatternRequest;
use Tests\TestCase;

class SaveTracedPatternRequestTest extends TestCase
{
    private function validate(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $req = new SaveTracedPatternRequest();
        $v = Validator::make($payload, $req->rules());
        $req->withValidator($v);

        return $v;
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Ceket',
            'calibration' => ['px_per_mm' => 3.78, 'image_height_px' => 1200],
            'pieces' => [[
                'name' => 'Ön', 'quantity' => 1,
                'polylines' => [[
                    'role' => 'cut',
                    'points' => [[0, 0], [100, 0], [100, 100]],
                ]],
            ]],
        ];
    }

    public function test_valid_passes(): void
    {
        $this->assertFalse($this->validate($this->validPayload())->fails());
    }

    public function test_missing_calibration_fails(): void
    {
        $p = $this->validPayload();
        unset($p['calibration']);
        $this->assertTrue($this->validate($p)->fails());
    }

    public function test_cut_with_two_points_fails(): void
    {
        $p = $this->validPayload();
        $p['pieces'][0]['polylines'][0]['points'] = [[0, 0], [10, 0]];
        $this->assertTrue($this->validate($p)->fails());
    }
}
