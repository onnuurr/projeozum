<?php

namespace Modules\PatternEngine\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\PatternEngine\Models\Assignment;

/**
 * Atölye iş akışı bildirimleri (Modül D). Tek sınıf, fabrika metodlarıyla.
 * 'database' kanalı → AppLayout'taki bildirim çanını besler.
 */
class AssignmentNotification extends Notification
{
    use Queueable;

    public const KIND_ASSIGNED  = 'assigned';
    public const KIND_DELIVERED  = 'delivered';
    public const KIND_REVIEWED   = 'reviewed';

    private function __construct(
        private Assignment $assignment,
        private string $kind,
        private ?bool $accepted = null,
    ) {}

    public static function assigned(Assignment $assignment): self
    {
        return new self($assignment, self::KIND_ASSIGNED);
    }

    public static function delivered(Assignment $assignment): self
    {
        return new self($assignment, self::KIND_DELIVERED);
    }

    public static function reviewed(Assignment $assignment, bool $accepted): self
    {
        return new self($assignment, self::KIND_REVIEWED, $accepted);
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        $a        = $this->assignment;
        $title    = $a->title;
        $assigner = $a->assigner?->name ?? __('patternengine::notifications.assignment.assigner_fallback');
        $assignee = $a->assignee?->name ?? __('patternengine::notifications.assignment.assignee_fallback');

        $payload = match (true) {
            $this->kind === self::KIND_ASSIGNED => [
                'icon' => '📋', 'iconType' => 'info',
                'title' => __('patternengine::notifications.assignment.assigned_title'),
                'desc'  => __('patternengine::notifications.assignment.assigned', ['assigner' => $assigner, 'title' => $title]),
                'link'  => '/atelier/assignments?mine=1',
            ],
            $this->kind === self::KIND_DELIVERED => [
                'icon' => '📦', 'iconType' => 'info',
                'title' => __('patternengine::notifications.assignment.delivered_title'),
                'desc'  => __('patternengine::notifications.assignment.delivered', ['assignee' => $assignee, 'title' => $title]),
                'link'  => '/atelier/assignments?status=delivered',
            ],
            $this->kind === self::KIND_REVIEWED && $this->accepted => [
                'icon' => '✅', 'iconType' => 'success',
                'title' => __('patternengine::notifications.assignment.accepted_title'),
                'desc'  => __('patternengine::notifications.assignment.accepted', ['title' => $title]),
                'link'  => '/atelier/assignments?mine=1',
            ],
            default => [
                'icon' => '↩️', 'iconType' => 'warning',
                'title' => __('patternengine::notifications.assignment.rejected_title'),
                'desc'  => $a->review_note
                    ? __('patternengine::notifications.assignment.rejected_with_note', ['title' => $title, 'note' => $a->review_note])
                    : __('patternengine::notifications.assignment.rejected', ['title' => $title]),
                'link'  => '/atelier/assignments?mine=1',
            ],
        };

        return $payload + ['kind' => $this->kind, 'assignment_id' => $a->id];
    }
}
