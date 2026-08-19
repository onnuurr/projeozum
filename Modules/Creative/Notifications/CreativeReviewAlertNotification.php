<?php

namespace Modules\Creative\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

/**
 * ReviewAlertEvaluator bir ret sinyalinin ART ARDA N raporda eşiği aştığını
 * tespit ettiğinde creative.approve iznine sahip tüm hesaplara gider —
 * CreativeReviewReportReadyNotification'dan farkı: "rapor hazır" değil,
 * "bir şeye bakman lazım" bildirimi (bkz. CreativeReviewReportCommand).
 */
class CreativeReviewAlertNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    /**
     * @param  array<int,array{subject:string,subject_label:string,type:string,label:string,rate:float,threshold:float,streak:int,sample_size:int}>  $alerts
     */
    public function __construct(
        private array $alerts,
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
        $top = $this->alerts[0];

        $desc = sprintf(
            '%s → %s: %%%d, %d gündür eşik (%%%d) üstünde.',
            $top['subject_label'],
            $top['label'],
            round($top['rate'] * 100),
            $top['streak'],
            round($top['threshold'] * 100),
        );

        if (count($this->alerts) > 1) {
            $desc .= sprintf(' (+%d uyarı daha)', count($this->alerts) - 1);
        }

        return [
            'icon'         => '⚠️',
            'iconType'     => 'warning',
            'title'        => 'Ret analizinde eşik aşıldı',
            'desc'         => $desc,
            'link'         => '/creative/review-reports/' . basename($this->reportPath),
            'kind'         => 'review_alert',
            'report_path'  => $this->reportPath,
        ];
    }
}
