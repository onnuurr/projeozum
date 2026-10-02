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
            ? __('creative::notifications.review_report.empty')
            : __('creative::notifications.review_report.summary', [
                'rejected' => $this->rejectedCount,
                'total' => $this->totalReviewedCount,
            ]) . ($this->topTag
                ? __('creative::notifications.review_report.top_tag', ['tag' => $this->topTag, 'count' => $this->topTagCount])
                : '');

        return [
            'icon'         => '📊',
            'iconType'     => 'info',
            'title'        => __('creative::notifications.review_report.title'),
            'desc'         => $desc,
            'link'         => '/creative/review-reports/' . basename($this->reportPath),
            'kind'         => 'review_report',
            'report_path'  => $this->reportPath,
        ];
    }
}
