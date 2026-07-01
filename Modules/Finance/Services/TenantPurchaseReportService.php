<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Tenant\Models\TenantInvoice;

/**
 * Tenant'ların bizden aldığı ürünlerin raporu. Tenant\TenantInvoice
 * (+ ilişkili Order, Tenant) tablolarını okur, hiçbir veri kopyalamaz.
 */
class TenantPurchaseReportService
{
    /**
     * @return array{invoiceCount: int, totalAmount: float, pendingAmount: float, paidAmount: float}
     */
    public function summary(): array
    {
        return [
            'invoiceCount'  => TenantInvoice::query()->count(),
            'totalAmount'   => (float) TenantInvoice::query()->sum('amount'),
            'pendingAmount' => (float) TenantInvoice::query()->where('status', 'pending')->sum('amount'),
            'paidAmount'    => (float) TenantInvoice::query()->where('status', 'paid')->sum('amount'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return TenantInvoice::query()
            ->with(['tenant:id,name,code', 'order:id,order_no'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantInvoice $invoice) => [
                'id'        => $invoice->id,
                'tenant'    => $invoice->tenant?->name ?? 'Bilinmiyor',
                'tenantCode'=> $invoice->tenant?->code,
                'orderNo'   => $invoice->order?->order_no,
                'amount'    => (float) $invoice->amount,
                'currency'  => $invoice->currency,
                'status'    => $invoice->status,
                'dueDate'   => optional($invoice->due_date)->format('Y-m-d'),
                'paidAt'    => optional($invoice->paid_at)->format('Y-m-d H:i'),
                'createdAt' => optional($invoice->created_at)->format('Y-m-d H:i'),
            ]);
    }
}
