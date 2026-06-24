<?php

namespace Modules\Atelier\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Atelier\Models\Assignment;

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
        $assigner = $a->assigner?->name ?? 'Bir koordinatör';
        $assignee = $a->assignee?->name ?? 'Atanan kişi';

        $payload = match (true) {
            $this->kind === self::KIND_ASSIGNED => [
                'icon' => '📋', 'iconType' => 'info',
                'title' => 'Yeni iş atandı',
                'desc'  => "{$assigner} sana “{$title}” işini atadı.",
                'link'  => '/atelier/assignments?mine=1',
            ],
            $this->kind === self::KIND_DELIVERED => [
                'icon' => '📦', 'iconType' => 'info',
                'title' => 'İş teslim edildi',
                'desc'  => "{$assignee} “{$title}” işini teslim etti.",
                'link'  => '/atelier/assignments?status=delivered',
            ],
            $this->kind === self::KIND_REVIEWED && $this->accepted => [
                'icon' => '✅', 'iconType' => 'success',
                'title' => 'İşin kabul edildi',
                'desc'  => "“{$title}” işin kabul edildi.",
                'link'  => '/atelier/assignments?mine=1',
            ],
            default => [
                'icon' => '↩️', 'iconType' => 'warning',
                'title' => 'İş geri gönderildi',
                'desc'  => "“{$title}” reddedildi" . ($a->review_note ? ": {$a->review_note}" : '.'),
                'link'  => '/atelier/assignments?mine=1',
            ],
        };

        return $payload + ['kind' => $this->kind, 'assignment_id' => $a->id];
    }
}
