<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\Models\OutgoingInvoice;

class OutgoingInvoiceService
{
    public function __construct(
        private readonly EInvoiceProviderInterface $eInvoiceProvider,
    ) {
    }

    /**
     * @return Collection<int, OutgoingInvoice>
     */
    public function list(): Collection
    {
        return OutgoingInvoice::query()
            ->with(['order:id,order_no', 'tenantInvoice:id,tenant_id', 'tenantInvoice.tenant:id,name', 'convertedFromProforma:id,proforma_no'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data, int $userId): OutgoingInvoice
    {
        return OutgoingInvoice::create([...$data, 'created_by' => $userId]);
    }

    public function update(OutgoingInvoice $invoice, array $data): OutgoingInvoice
    {
        $invoice->update($data);

        return $invoice->fresh();
    }

    public function delete(OutgoingInvoice $invoice): void
    {
        $invoice->delete();
    }

    /**
     * e-Fatura sağlayıcısına gönderim isteği. Somut entegratör bağlanana kadar
     * NullEInvoiceProvider devrede olduğu için kayıt 'not_sent' kalmaya devam eder.
     */
    public function send(OutgoingInvoice $invoice): OutgoingInvoice
    {
        $result = $this->eInvoiceProvider->send($invoice);

        $invoice->update([
            'efatura_uuid'          => $result->uuid,
            'efatura_status'        => $result->status,
            'efatura_raw_response'  => $result->rawResponse,
            'sent_at'               => $result->status !== OutgoingInvoice::EFATURA_NOT_SENT ? now() : null,
        ]);

        return $invoice->fresh();
    }

    /**
     * @return array{invoiceCount: int, notSentCount: int}
     */
    public function summary(): array
    {
        return [
            'invoiceCount' => OutgoingInvoice::query()->count(),
            'notSentCount' => OutgoingInvoice::query()
                ->where('efatura_status', OutgoingInvoice::EFATURA_NOT_SENT)
                ->count(),
        ];
    }
}
