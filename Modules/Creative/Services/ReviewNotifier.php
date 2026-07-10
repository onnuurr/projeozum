<?php

namespace Modules\Creative\Services;

use App\Models\User;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Notifications\ImagePendingReviewNotification;
use Modules\Creative\Notifications\ImageReviewDecisionNotification;

/**
 * Onay akışı bildirimlerini tek noktadan gönderir (üretim servisleri + review
 * controller'ları tarafından kullanılır).
 */
class ReviewNotifier
{
    /**
     * Görsel onaya düştüğünde, üreten hesap HARİÇ creative.approve sahibi
     * tüm hesaplara bildirir (açık havuz — atama yok).
     */
    public function notifyPending(Mannequin|TryonResult $subject, ?int $creatorId): void
    {
        $admins = User::permission('creative.approve')->get();

        if ($creatorId !== null) {
            $admins = $admins->reject(fn (User $u) => $u->id === $creatorId);
        }

        foreach ($admins as $admin) {
            $admin->notify(new ImagePendingReviewNotification($subject));
        }
    }

    /**
     * Onay/ret kararını üretici hesaba bildirir.
     */
    public function notifyDecision(Mannequin|TryonResult $subject, bool $approved): void
    {
        $creator = $subject->creator;

        $creator?->notify(new ImageReviewDecisionNotification($subject, $approved));
    }
}
