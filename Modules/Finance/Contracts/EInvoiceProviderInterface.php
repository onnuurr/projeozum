<?php

namespace Modules\Finance\Contracts;

use Modules\Finance\DTO\EInvoiceSendResult;
use Modules\Finance\DTO\EInvoiceStatusResult;
use Modules\Finance\Models\OutgoingInvoice;

/**
 * GİB e-Fatura/e-Arşiv entegratörü (Foriba/Nilvera/İzibiz vb.) henüz seçilmediği
 * için somut bir sürücü yok. Tek sürücü Modules\Finance\Services\EInvoice\NullEInvoiceProvider
 * — hiçbir dış istek atmaz, kaydı 'not_sent' bırakır. İleride gerçek entegratör
 * seçilince FinanceServiceProvider::register() içindeki binding değiştirilir.
 */
interface EInvoiceProviderInterface
{
    public function send(OutgoingInvoice $invoice): EInvoiceSendResult;

    public function checkStatus(OutgoingInvoice $invoice): EInvoiceStatusResult;

    public function cancel(OutgoingInvoice $invoice): bool;
}
