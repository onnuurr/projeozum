<?php

namespace Modules\Finance\Services\BankImport;

use Illuminate\Support\Collection;
use Modules\Finance\Models\BankTransaction;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Finance\Models\SupplierInvoice;
use Modules\Tenant\Models\TenantInvoice;

/**
 * Banka işlemlerini mevcut fatura tablolarıyla (yalnızca okuma) eşleştirir.
 * Hiçbir veri kopyalamaz; yalnızca `finance_bank_transactions.matched_invoice_*`
 * kolonlarını doldurur.
 */
class ReconciliationService
{
    /**
     * @return Collection<int, BankTransaction>
     */
    public function unmatchedTransactions(): Collection
    {
        return BankTransaction::query()
            ->with('bankAccount:id,bank_name,account_name')
            ->where('reconciliation_status', BankTransaction::STATUS_UNMATCHED)
            ->orderByDesc('transaction_date')
            ->get();
    }

    /**
     * Basit sezgisel eşleştirme: tutar tam eşleşen, kapanmamış faturaları aday olarak döner.
     *
     * @return array<int, array{type: string, id: int, label: string}>
     */
    public function suggestCandidates(BankTransaction $transaction): array
    {
        $amount     = abs((float) $transaction->amount);
        $candidates = [];

        if ($transaction->amount < 0) {
            // Çıkış → muhtemelen bir tedarikçi faturası ödemesi.
            SupplierInvoice::query()
                ->where('total', $amount)
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->limit(5)
                ->get()
                ->each(function (SupplierInvoice $invoice) use (&$candidates) {
                    $candidates[] = [
                        'type'  => BankTransaction::MATCH_SUPPLIER_INVOICE,
                        'id'    => $invoice->id,
                        'label' => "{$invoice->invoice_no} — {$invoice->supplier_name}",
                    ];
                });

            return $candidates;
        }

        // Giriş → düzenlediğimiz fatura veya tenant tahsilatı olabilir.
        OutgoingInvoice::query()
            ->where('total', $amount)
            ->limit(5)
            ->get()
            ->each(function (OutgoingInvoice $invoice) use (&$candidates) {
                $candidates[] = [
                    'type'  => BankTransaction::MATCH_OUTGOING_INVOICE,
                    'id'    => $invoice->id,
                    'label' => "{$invoice->invoice_no} — {$invoice->buyer_name}",
                ];
            });

        TenantInvoice::query()
            ->where('amount', $amount)
            ->where('status', '!=', 'paid')
            ->with('tenant:id,name')
            ->limit(5)
            ->get()
            ->each(function (TenantInvoice $invoice) use (&$candidates) {
                $candidates[] = [
                    'type'  => BankTransaction::MATCH_TENANT_INVOICE,
                    'id'    => $invoice->id,
                    'label' => 'Tenant faturası #' . $invoice->id . ' — ' . ($invoice->tenant?->name ?? 'Bilinmiyor'),
                ];
            });

        return $candidates;
    }

    public function confirmMatch(BankTransaction $transaction, string $type, int $invoiceId, int $userId): BankTransaction
    {
        $transaction->update([
            'reconciliation_status' => BankTransaction::STATUS_MATCHED,
            'matched_invoice_type'  => $type,
            'matched_invoice_id'    => $invoiceId,
            'matched_at'            => now(),
            'matched_by'            => $userId,
        ]);

        return $transaction->fresh();
    }

    public function ignore(BankTransaction $transaction): BankTransaction
    {
        $transaction->update(['reconciliation_status' => BankTransaction::STATUS_IGNORED]);

        return $transaction->fresh();
    }

    public function unmatchedCount(): int
    {
        return BankTransaction::query()
            ->where('reconciliation_status', BankTransaction::STATUS_UNMATCHED)
            ->count();
    }
}
