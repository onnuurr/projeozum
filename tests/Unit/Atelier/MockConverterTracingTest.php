<?php

namespace Tests\Unit\Atelier;

use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;
use Tests\TestCase;

class MockConverterTracingTest extends TestCase
{
    public function test_mock_render_and_build_dxf(): void
    {
        $mock = new MockPdfDxfConverter();

        $png = $mock->renderPage('/yok/x.pdf', 0, 200);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $dxf = $mock->buildDxf([
            ['role' => 'cut', 'points' => [[0, 0], [10, 0], [10, 10]], 'closed' => true],
        ]);
        $this->assertStringContainsString('EOF', $dxf);
    }
}
