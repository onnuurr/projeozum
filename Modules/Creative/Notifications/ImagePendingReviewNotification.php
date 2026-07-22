<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

/**
 * Üretilen manken/giydirme/creative tasarımı onaya düştüğünde, üretici hesap
 * HARİÇ creative.approve iznine sahip tüm hesaplara gider (database kanalı →
 * AppLayout bildirim çanı). Atama yok; açık havuz, ilk davranan işler.
 */
class ImagePendingReviewNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private Mannequin|TryonResult|CreativeAsset $subject) {}

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
        $link = match ($subjectType) {
            'mannequin' => '/creative/mannequins',
            'asset'     => '/creative/gallery',
            default     => '/creative/tryon',
        };
        $desc = match ($subjectType) {
            'mannequin' => "\"{$label}\" mankeni onayınızı bekliyor.",
            'asset'     => "\"{$label}\" için üretilen sosyal medya tasarımı onayınızı bekliyor.",
            default     => "\"{$label}\" için üretilen giydirme görseli onayınızı bekliyor.",
        };

        return [
            'icon'         => '🕐',
            'iconType'     => 'info',
            'title'        => 'Onay bekleyen görsel',
            'desc'         => $desc,
            'link'         => $link,
            'kind'         => 'pending_review',
            'subject_type' => $subjectType,
            'subject_id'   => $this->subject->id,
        ];
    }
}
