<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Models\Assignment;
use Modules\Atelier\Models\Pattern;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;
    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.assignment.manage', 'guard_name' => 'web']);

        $this->coordinator = User::factory()->create();
        $this->coordinator->givePermissionTo('atelier.view', 'atelier.assignment.manage');
        $this->maker = User::factory()->create();
        $this->maker->givePermissionTo('atelier.view'); // sadece atanan kişi, yönetici değil
    }

    private function pattern(): Pattern
    {
        return Pattern::create(['name' => 'T', 'product_type' => 'tulum', 'status' => Pattern::STATUS_APPROVED]);
    }

    public function test_coordinator_assigns_work(): void
    {
        $this->actingAs($this->coordinator)
            ->post('/atelier/assignments', [
                'pattern_id' => $this->pattern()->id,
                'kind'       => Assignment::KIND_PATTERN_MAKER,
                'assigned_to'=> $this->maker->id,
                'title'      => 'Kalıbı çıkar',
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('atelier_assignments', [
            'assigned_to' => $this->maker->id, 'assigned_by' => $this->coordinator->id,
            'status' => Assignment::STATUS_PENDING,
        ]);
    }

    public function test_assignee_can_start_and_deliver_but_not_accept(): void
    {
        $a = Assignment::create([
            'pattern_id' => $this->pattern()->id, 'assigned_to' => $this->maker->id,
            'kind' => Assignment::KIND_PATTERN_MAKER, 'title' => 'İş', 'status' => Assignment::STATUS_PENDING,
        ]);

        $this->actingAs($this->maker)->post("/atelier/assignments/{$a->id}/start")->assertRedirect();
        $this->assertSame(Assignment::STATUS_IN_PROGRESS, $a->fresh()->status);

        $this->actingAs($this->maker)
            ->post("/atelier/assignments/{$a->id}/deliver", ['file' => UploadedFile::fake()->create('r.dxf', 10)])
            ->assertRedirect();
        $this->assertSame(Assignment::STATUS_DELIVERED, $a->fresh()->status);

        // Atanan kişi kabul edemez (yönetici izni yok → route can: kapısı).
        $this->actingAs($this->maker)->post("/atelier/assignments/{$a->id}/accept")->assertForbidden();
    }

    public function test_other_user_cannot_act_on_assignment(): void
    {
        $other = User::factory()->create();
        $other->givePermissionTo('atelier.view');
        $a = Assignment::create([
            'pattern_id' => $this->pattern()->id, 'assigned_to' => $this->maker->id,
            'kind' => Assignment::KIND_PATTERN_MAKER, 'title' => 'İş', 'status' => Assignment::STATUS_PENDING,
        ]);

        // Başkası (atanan değil, yönetici değil) başlatamaz.
        $this->actingAs($other)->post("/atelier/assignments/{$a->id}/start")->assertForbidden();
    }

    public function test_coordinator_accepts_delivery(): void
    {
        $a = Assignment::create([
            'pattern_id' => $this->pattern()->id, 'assigned_to' => $this->maker->id,
            'kind' => Assignment::KIND_PATTERN_MAKER, 'title' => 'İş', 'status' => Assignment::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->actingAs($this->coordinator)
            ->post("/atelier/assignments/{$a->id}/accept", ['note' => 'iyi'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(Assignment::STATUS_ACCEPTED, $a->fresh()->status);
    }
}
