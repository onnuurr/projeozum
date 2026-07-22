<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

/**
 * Bir yönetici manken/giydirme/creative tasarımını onaylayınca ya da
 * (gerekçeyle) reddedince, üretici hesaba gider (database kanalı → AppLayout
 * bildirim çanı).
 */
class ImageReviewDecisionNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        private Mannequin|TryonResult|CreativeAsset $subject,
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
        $subjectType = match (true) {
            $this->subject instanceof Mannequin => 'mannequin',
            $this->subject instanceof CreativeAsset => 'asset',
            default => 'tryon',
        };
        $label = $this->subject instanceof Mannequin
            ? $this->subject->name
            : ($this->subject->product?->name ?? 'Ürün');
        $type = match ($subjectType) {
            'mannequin' => 'Manken',
            'asset'     => 'Sosyal medya tasarımı',
            default     => 'Giydirme görseli',
        };
        $link = match ($subjectType) {
            'mannequin' => '/creative/mannequins',
            'asset'     => '/creative/gallery',
            default     => '/creative/tryon',
        };

        $payload = [
            'link'         => $link,
            'kind'         => 'review_decision',
            'approved'     => $this->approved,
            'subject_type' => $subjectType,
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
