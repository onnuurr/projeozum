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
            : ($this->subject->product?->name ?? __('creative::labels.product_fallback'));
        $link = match ($subjectType) {
            'mannequin' => '/creative/mannequins',
            'asset'     => '/creative/gallery',
            default     => '/creative/tryon',
        };
        $desc = __('creative::notifications.pending_review.'.$subjectType, ['label' => $label]);

        return [
            'icon'         => '🕐',
            'iconType'     => 'info',
            'title'        => __('creative::notifications.pending_review.title'),
            'desc'         => $desc,
            'link'         => $link,
            'kind'         => 'pending_review',
            'subject_type' => $subjectType,
            'subject_id'   => $this->subject->id,
        ];
    }
}
