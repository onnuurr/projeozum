<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Atelier\Models\Assignment;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\AssignmentService;
use Tests\TestCase;

class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function service(): AssignmentService
    {
        return app(AssignmentService::class);
    }

    private function assignment(): Assignment
    {
        $pattern = Pattern::create(['name' => 'T', 'product_type' => 'tulum', 'status' => Pattern::STATUS_APPROVED]);
        $user = User::factory()->create();

        return $this->service()->assign([
            'pattern_id' => $pattern->id, 'assigned_to' => $user->id,
            'kind' => Assignment::KIND_PATTERN_MAKER, 'title' => 'Kalıbı revize et',
        ]);
    }

    public function test_assign_requires_card_or_pattern(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->service()->assign(['assigned_to' => $user->id, 'title' => 'X']);
    }

    public function test_happy_path_pending_to_accepted(): void
    {
        $a = $this->assignment();
        $this->assertSame(Assignment::STATUS_PENDING, $a->status);

        $a = $this->service()->start($a);
        $this->assertSame(Assignment::STATUS_IN_PROGRESS, $a->status);

        $a = $this->service()->deliver($a, UploadedFile::fake()->create('revize.dxf', 10), 'tamam');
        $this->assertSame(Assignment::STATUS_DELIVERED, $a->status);
        $this->assertNotNull($a->delivered_at);
        Storage::disk('public')->assertExists($a->delivered_file_path);

        $a = $this->service()->accept($a, 'onaylandı');
        $this->assertSame(Assignment::STATUS_ACCEPTED, $a->status);
        $this->assertNotNull($a->accepted_at);
    }

    public function test_reject_returns_to_work_then_can_redeliver(): void
    {
        $a = $this->service()->start($this->assignment());
        $a = $this->service()->deliver($a);
        $a = $this->service()->reject($a, 'ölçü tutmuyor');
        $this->assertSame(Assignment::STATUS_REJECTED, $a->status);

        // Reddedilen iş tekrar başlatılıp teslim edilebilir.
        $a = $this->service()->start($a);
        $this->assertSame(Assignment::STATUS_IN_PROGRESS, $a->status);
    }

    public function test_cannot_accept_before_delivery(): void
    {
        $a = $this->service()->start($this->assignment());
        $this->expectException(InvalidArgumentException::class);
        $this->service()->accept($a);
    }
}
