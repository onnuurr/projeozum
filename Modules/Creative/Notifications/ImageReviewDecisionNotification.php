<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

/**
 * Bir yönetici manken/giydirme görselini onaylayınca ya da (gerekçeyle)
 * reddedince, üretici hesaba gider (database kanalı → AppLayout bildirim çanı).
 */
class ImageReviewDecisionNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        private Mannequin|TryonResult $subject,
        private bool $approved,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        $isMannequin = $this->subject instanceof Mannequin;
        $label       = $isMannequin ? $this->subject->name : ($this->subject->product?->name ?? 'Ürün');
        $type        = $isMannequin ? 'Manken' : 'Giydirme görseli';
        $link        = $isMannequin ? '/creative/mannequins' : '/creative/tryon';

        $payload = [
            'link'         => $link,
            'kind'         => 'review_decision',
            'approved'     => $this->approved,
            'subject_type' => $isMannequin ? 'mannequin' : 'tryon',
            'subject_id'   => $this->subject->id,
        ];

        if ($this->approved) {
            return $payload + [
                'icon'     => '✅',
                'iconType' => 'success',
                'title'    => 'Görsel onaylandı',
                'desc'     => "{$type} \"{$label}\" onaylandı.",
            ];
        }

        return $payload + [
            'icon'     => '↩️',
            'iconType' => 'warning',
            'title'    => 'Görsel reddedildi',
            'desc'     => "{$type} \"{$label}\" reddedildi" . ($this->subject->review_note ? ": {$this->subject->review_note}" : '.'),
        ];
    }
}
