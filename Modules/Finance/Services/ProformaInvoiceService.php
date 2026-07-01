<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Finance\Models\ProformaInvoice;

class ProformaInvoiceService
{
    /**
     * @return Collection<int, ProformaInvoice>
     */
    public function list(): Collection
    {
        return ProformaInvoice::query()
            ->with(['order:id,order_no', 'tenantInvoice:id,tenant_id', 'tenantInvoice.tenant:id,name', 'items'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items Yalnızca standalone (order/tenant_invoice'a bağlı olmayan) proformalar için.
     */
    public function create(array $data, array $items, int $userId): ProformaInvoice
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $proforma = ProformaInvoice::create([...$data, 'created_by' => $userId]);

            if (! $proforma->order_id && ! $proforma->tenant_invoice_id) {
                $proforma->items()->createMany($items);
            }

            return $proforma->load('items');
        });
    }

    public function update(ProformaInvoice $proforma, array $data, array $items): ProformaInvoice
    {
        return DB::transaction(function () use ($proforma, $data, $items) {
            $proforma->update($data);

            if (! $proforma->order_id && ! $proforma->tenant_invoice_id) {
                $proforma->items()->delete();
                $proforma->items()->createMany($items);
            }

            return $proforma->fresh('items');
        });
    }

    public function delete(ProformaInvoice $proforma): void
    {
        $proforma->delete();
    }

    /**
     * Proformayı, kendi üzerinde referans tuttuğu order/tenant_invoice bilgisini
     * taşıyan yeni bir düzenlenen faturaya (OutgoingInvoice) dönüştürür.
     * Proforma verisi kopyalanmaz — yalnızca özet alanlar yeni kayda taşınır,
     * `converted_invoice_id` ile ileriye referans kurulur.
     */
    public function convertToOutgoingInvoice(ProformaInvoice $proforma, int $userId): OutgoingInvoice
    {
        return DB::transaction(function () use ($proforma, $userId) {
            $invoiceType = match (true) {
                (bool) $proforma->order_id          => OutgoingInvoice::TYPE_SALES_ORDER,
                (bool) $proforma->tenant_invoice_id  => OutgoingInvoice::TYPE_TENANT_SALE,
                default                              => OutgoingInvoice::TYPE_STANDALONE,
            };

            $invoice = OutgoingInvoice::create([
                'invoice_no'                 => 'INV-' . $proforma->proforma_no,
                'invoice_type'               => $invoiceType,
                'order_id'                   => $proforma->order_id,
                'tenant_invoice_id'          => $proforma->tenant_invoice_id,
                'buyer_name'                 => $proforma->buyer_name,
                'buyer_tax_number'           => $proforma->buyer_tax_number,
                'issue_date'                 => now()->toDateString(),
                'currency'                   => $proforma->currency,
                'subtotal'                   => $proforma->subtotal,
                'tax_amount'                 => $proforma->tax_amount,
                'total'                      => $proforma->total,
                'converted_from_proforma_id' => $proforma->id,
                'created_by'                 => $userId,
            ]);

            $proforma->update([
                'converted_invoice_id' => $invoice->id,
                'status'               => ProformaInvoice::STATUS_CONVERTED,
            ]);

            return $invoice;
        });
    }

    /**
     * `valid_until` tarihi geçmiş, hâlâ draft/sent durumundaki proformaları
     * `expired` yapar. `finance:expire-proformas` zamanlanmış komutu tarafından çağrılır.
     */
    public function expireOverdue(): int
    {
        return ProformaInvoice::query()
            ->whereIn('status', [ProformaInvoice::STATUS_DRAFT, ProformaInvoice::STATUS_SENT])
            ->whereDate('valid_until', '<', now()->toDateString())
            ->update(['status' => ProformaInvoice::STATUS_EXPIRED]);
    }

    /**
     * @return array{invoiceCount: int, openCount: int}
     */
    public function summary(): array
    {
        return [
            'invoiceCount' => ProformaInvoice::query()->count(),
            'openCount'    => ProformaInvoice::query()
                ->whereIn('status', [ProformaInvoice::STATUS_DRAFT, ProformaInvoice::STATUS_SENT, ProformaInvoice::STATUS_ACCEPTED])
                ->count(),
        ];
    }
}
