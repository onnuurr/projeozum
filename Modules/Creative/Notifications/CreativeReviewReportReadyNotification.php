<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

/**
 * Haftalık ret analiz raporu (creative:review-report) üretildiğinde creative.approve
 * iznine sahip tüm hesaplara gider — ImagePendingReviewNotification ile aynı kanal
 * deseni (database + broadcast → AppLayout bildirim çanı).
 */
class CreativeReviewReportReadyNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        private int $rejectedCount,
        private int $totalReviewedCount,
        private ?string $topTag,
        private int $topTagCount,
        private string $reportPath,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        $desc = $this->totalReviewedCount === 0
            ? 'Bu hafta incelenen görsel yok.'
            : sprintf(
                '%d/%d görsel reddedildi.%s',
                $this->rejectedCount,
                $this->totalReviewedCount,
                $this->topTag ? " En sık neden: \"{$this->topTag}\" ({$this->topTagCount})." : '',
            );

        return [
            'icon'         => '📊',
            'iconType'     => 'info',
            'title'        => 'Haftalık giydirme ret raporu hazır',
            'desc'         => $desc,
            'link'         => '/creative/review-reports/' . basename($this->reportPath),
            'kind'         => 'review_report',
            'report_path'  => $this->reportPath,
        ];
    }
}
