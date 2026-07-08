<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Finance\Models\SupplierInvoice;

class SupplierInvoiceService
{
    /**
     * @return Collection<int, SupplierInvoice>
     */
    public function list(): Collection
    {
        return SupplierInvoice::query()
            ->with('productionOrder:id,code')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data, int $userId): SupplierInvoice
    {
        return SupplierInvoice::create([...$data, 'created_by' => $userId]);
    }

    public function update(SupplierInvoice $invoice, array $data): SupplierInvoice
    {
        $invoice->update($data);

        return $invoice->fresh();
    }

    public function delete(SupplierInvoice $invoice): void
    {
        $invoice->delete();
    }

    public function markAsPaid(SupplierInvoice $invoice): SupplierInvoice
    {
        $invoice->update([
            'status'  => SupplierInvoice::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $invoice->fresh();
    }

    /**
     * @return array{invoiceCount: int, unpaidAmount: float}
     */
    public function summary(): array
    {
        return [
            'invoiceCount' => SupplierInvoice::query()->count(),
            'unpaidAmount' => (float) SupplierInvoice::query()
                ->whereIn('status', [SupplierInvoice::STATUS_UNPAID, SupplierInvoice::STATUS_PARTIALLY_PAID])
                ->sum('total'),
        ];
    }
}
