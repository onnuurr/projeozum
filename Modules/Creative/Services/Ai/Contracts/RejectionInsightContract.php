<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Haftalık ret analiz raporundaki etiket/sürücü/not özetinden "ne yapılabilir"
 * önerisi üreten sürücü sözleşmesi (bkz. CreativeReviewReportCommand).
 */
interface RejectionInsightContract
{
    /**
     * @param  array<string,mixed>  $tryonSummary   CreativeReviewReportCommand::summarizeSubject() çıktısı
     * @param  array<string,mixed>  $mannequinSummary
     */
    public function generate(array $tryonSummary, array $mannequinSummary): string;
}
