<?php

namespace Modules\Finance\Services\EInvoice;

use Illuminate\Support\Facades\Log;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\DTO\EInvoiceSendResult;
use Modules\Finance\DTO\EInvoiceStatusResult;
use Modules\Finance\Models\OutgoingInvoice;

/**
 * Entegratör seçilene kadar tek e-Fatura sürücüsü. Hiçbir dış istek atmaz;
 * fatura 'not_sent' durumunda kalmaya devam eder.
 */
class NullEInvoiceProvider implements EInvoiceProviderInterface
{
    public function send(OutgoingInvoice $invoice): EInvoiceSendResult
    {
        Log::info('e-Fatura sağlayıcısı henüz yapılandırılmadı; gönderim atlandı.', [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
        ]);

        return new EInvoiceSendResult(uuid: null, status: OutgoingInvoice::EFATURA_NOT_SENT);
    }

    public function checkStatus(OutgoingInvoice $invoice): EInvoiceStatusResult
    {
        return new EInvoiceStatusResult(status: OutgoingInvoice::EFATURA_NOT_SENT);
    }

    public function cancel(OutgoingInvoice $invoice): bool
    {
        return false;
    }
}
