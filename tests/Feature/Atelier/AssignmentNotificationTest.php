<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Atelier\Models\Assignment;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Notifications\AssignmentNotification;
use Modules\Atelier\Services\AssignmentService;
use Tests\TestCase;

class AssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AssignmentService
    {
        return app(AssignmentService::class);
    }

    private function makeAssignment(User $assignee, ?User $assigner = null): Assignment
    {
        $pattern = Pattern::create(['name' => 'T', 'product_type' => 'tulum', 'status' => Pattern::STATUS_APPROVED]);

        return $this->service()->assign([
            'pattern_id' => $pattern->id, 'assigned_to' => $assignee->id,
            'kind' => Assignment::KIND_PATTERN_MAKER, 'title' => 'Kalıbı çıkar',
        ], $assigner?->id);
    }

    public function test_assign_notifies_assignee(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();

        $this->makeAssignment($assignee);

        Notification::assertSentTo($assignee, AssignmentNotification::class);
    }

    public function test_deliver_notifies_assigner(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $assigner = User::factory()->create();
        $a = $this->makeAssignment($assignee, $assigner);
        $this->service()->start($a);

        $this->service()->deliver($a);

        Notification::assertSentTo($assigner, AssignmentNotification::class);
    }

    public function test_accept_and_reject_notify_assignee(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $assigner = User::factory()->create();

        $a = $this->makeAssignment($assignee, $assigner);
        $a = $this->service()->start($a);
        $a = $this->service()->deliver($a);
        $this->service()->accept($a);

        $b = $this->makeAssignment($assignee, $assigner);
        $b = $this->service()->start($b);
        $b = $this->service()->deliver($b);
        $this->service()->reject($b, 'ölçü tutmuyor');

        // assign(2) + accept(1) + reject(1) = assignee'ye en az 4 bildirim
        Notification::assertSentToTimes($assignee, AssignmentNotification::class, 4);
    }

    public function test_read_endpoint_marks_notification_read(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        // Gerçek DB bildirimi üret (fake yok).
        $this->makeAssignment($assignee);
        $notification = $assignee->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $this->actingAs($assignee)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_all_endpoint_marks_all_read(): void
    {
        $assignee = User::factory()->create();
        $this->makeAssignment($assignee);
        $this->makeAssignment($assignee);
        $this->assertSame(2, $assignee->unreadNotifications()->count());

        $this->actingAs($assignee)
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $assignee->fresh()->unreadNotifications()->count());
    }
}
