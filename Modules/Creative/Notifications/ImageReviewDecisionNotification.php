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
            : ($this->subject->product?->name ?? __('creative::labels.product_fallback'));
        $type = __('creative::labels.subject_type.'.$subjectType);
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
                'title'    => __('creative::notifications.review_decision.approved_title'),
                'desc'     => __('creative::notifications.review_decision.approved', ['type' => $type, 'label' => $label]),
            ];
        }

        return $payload + [
            'icon'     => '↩️',
            'iconType' => 'warning',
            'title'    => __('creative::notifications.review_decision.rejected_title'),
            'desc'     => $this->subject->review_note
                ? __('creative::notifications.review_decision.rejected_with_note', ['type' => $type, 'label' => $label, 'note' => $this->subject->review_note])
                : __('creative::notifications.review_decision.rejected', ['type' => $type, 'label' => $label]),
        ];
    }
}
