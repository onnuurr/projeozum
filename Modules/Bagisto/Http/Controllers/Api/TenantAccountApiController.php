<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Bagisto\Services\BagistoOrderSyncService;
use Modules\Tenant\Models\TenantCreditLedger;
use Modules\Tenant\Models\TenantInvoice;

/**
 * Bayi portalı (Bagisto ozmbayi) "Cari Hesap" ekranı: limit/bakiye özeti, açık faturalar ve
 * cari hareketleri (tenant_credit_ledger). Bayi, Bagisto müşteri e-postasıyla eşlenir —
 * sipariş webhook'uyla aynı kural (BagistoOrderSyncService::resolveTenantForEmail).
 */
class TenantAccountApiController extends Controller
{
    private const REASONS = [
        'bagisto_order'   => 'Sipariş',
        'order_cancelled' => 'Sipariş iptali (iade)',
        'invoice_paid'    => 'Fatura ödemesi',
    ];

    public function __construct(private BagistoOrderSyncService $sync) {}

    public function statement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenant = $this->sync->resolveTenantForEmail($data['email']);

        if (! $tenant) {
            return response()->json(['message' => 'Bayi bulunamadı.'], 404);
        }

        $entries = TenantCreditLedger::with('order:id,bagisto_order_id,order_no')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->paginate(20);

        $openInvoices = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get(['id', 'amount', 'currency', 'status', 'due_date', 'note']);

        return response()->json([
            'account' => [
                'credit_limit' => (float) $tenant->credit_limit,
                'current_balance' => (float) $tenant->current_balance,
                'available' => max(0, (float) $tenant->credit_limit - (float) $tenant->current_balance),
                'payment_term_days' => (int) $tenant->payment_term_days,
            ],
            'open_invoices' => $openInvoices->map(fn (TenantInvoice $invoice) => [
                'id' => $invoice->id,
                'amount' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'due_date' => $invoice->due_date?->toDateString(),
                'overdue' => $invoice->due_date && $invoice->due_date->isPast(),
                'note' => $invoice->note,
            ])->values(),
            'entries' => collect($entries->items())->map(fn (TenantCreditLedger $entry) => [
                'id' => $entry->id,
                'date' => $entry->created_at?->toIso8601String(),
                'type' => $entry->type,
                'amount' => (float) $entry->amount,
                'reason' => $entry->reason,
                'reason_label' => self::REASONS[$entry->reason] ?? $entry->reason,
                'bagisto_order_id' => $entry->order?->bagisto_order_id,
                'order_no' => $entry->order?->order_no,
                'invoice_id' => $entry->invoice_id,
                'balance_after' => (float) $entry->balance_after,
            ])->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }
}
