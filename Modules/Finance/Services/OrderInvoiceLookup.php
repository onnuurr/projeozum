<?php

namespace Modules\Finance\Services;

use App\Contracts\OrderInvoiceLookup as OrderInvoiceLookupContract;
use Modules\Finance\Models\OutgoingInvoice;

/**
 * Siparişe bağlı, kesilmiş (taslak/iptal/reddedilmiş olmayan) bir satış faturası var mı.
 * `ready` da sayılır: fatura düzenlenmiş, GİB'e gönderilmeyi bekliyor.
 */
class OrderInvoiceLookup implements OrderInvoiceLookupContract
{
    public function hasIssuedInvoice(int $orderId): bool
    {
        return OutgoingInvoice::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [
                OutgoingInvoice::STATUS_READY,
                OutgoingInvoice::STATUS_SENT,
                OutgoingInvoice::STATUS_ACCEPTED,
            ])
            ->exists();
    }
}
