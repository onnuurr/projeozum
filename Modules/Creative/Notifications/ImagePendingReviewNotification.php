<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

/**
 * Üretilen manken/giydirme görseli onaya düştüğünde, üretici hesap HARİÇ
 * creative.approve iznine sahip tüm hesaplara gider (database kanalı →
 * AppLayout bildirim çanı). Atama yok; açık havuz, ilk davranan işler.
 */
class ImagePendingReviewNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private Mannequin|TryonResult $subject) {}

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

        return [
            'icon'         => '🕐',
            'iconType'     => 'info',
            'title'        => 'Onay bekleyen görsel',
            'desc'         => $isMannequin
                ? "\"{$label}\" mankeni onayınızı bekliyor."
                : "\"{$label}\" için üretilen giydirme görseli onayınızı bekliyor.",
            'link'         => $isMannequin ? '/creative/mannequins' : '/creative/tryon',
            'kind'         => 'pending_review',
            'subject_type' => $isMannequin ? 'mannequin' : 'tryon',
            'subject_id'   => $this->subject->id,
        ];
    }
}
