<?php

namespace Modules\Bagisto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bagisto\Exceptions\OrderReturnException;
use Modules\Bagisto\Services\BagistoReturnService;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Siparişler › İadeler: Bagisto'dan gelen bayi iade taleplerinin SaaS'taki yönetimi
 * (karar, iade faturası kontrolü, para iadesi). İş kuralları BagistoReturnService'te.
 */
class OrderReturnController extends Controller
{
    public function __construct(private BagistoReturnService $returns) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(OrderReturn::statuses())).',awaiting_invoice,invoice_review,ready_to_refund'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = OrderReturn::query()
            ->with('order:id,order_no,total,payment_method')
            ->latest('id');

        match ($filters['status'] ?? null) {
            null => null,
            'awaiting_invoice' => $query->where('status', OrderReturn::STATUS_APPROVED)
                ->whereIn('invoice_status', [OrderReturn::INVOICE_AWAITING, OrderReturn::INVOICE_REJECTED]),
            'invoice_review' => $query->where('status', OrderReturn::STATUS_APPROVED)
                ->where('invoice_status', OrderReturn::INVOICE_PENDING),
            'ready_to_refund' => $query->where('status', OrderReturn::STATUS_APPROVED)
                ->where(fn ($q) => $q->where('invoice_required', false)->orWhere('invoice_status', OrderReturn::INVOICE_ACCEPTED)),
            default => $query->where('status', $filters['status']),
        };

        if (filled($filters['search'] ?? null)) {
            $term = '%'.mb_strtolower($filters['search']).'%';
            $query->where(fn ($q) => $q
                ->where('bagisto_rma_id', $filters['search'])
                ->orWhereHas('order', fn ($o) => $o->whereRaw('lower(order_no) like ?', [$term]))
                ->orWhereIn('tenant_id', Tenant::query()->whereRaw('lower(name) like ?', [$term])->select('id')));
        }

        $approved = OrderReturn::query()->where('status', OrderReturn::STATUS_APPROVED);

        $page = $query->paginate(25)->withQueryString();
        $tenants = Tenant::query()->whereIn('id', $page->pluck('tenant_id')->filter()->unique())->pluck('name', 'id');

        return Inertia::render('Bagisto::OrderReturns', [
            'returns' => $page->through(fn (OrderReturn $r) => [
                'id' => $r->id,
                'rma_id' => $r->bagisto_rma_id,
                'order_id' => $r->order_id,
                'order_no' => $r->order?->order_no ?? '#'.$r->bagisto_order_id,
                'tenant' => $tenants[$r->tenant_id] ?? null,
                'reason' => $r->reason,
                'item_count' => collect($r->items)->sum('qty'),
                'requested_amount' => (float) $r->requested_amount,
                'approved_amount' => $r->approved_amount !== null ? (float) $r->approved_amount : null,
                'status' => $r->status,
                'invoice_status' => $r->invoice_status,
                'inspection_result' => $r->inspection['result'] ?? null,
                'created_at' => optional($r->bagisto_created_at ?? $r->created_at)->toIso8601String(),
            ]),
            'stats' => [
                'awaiting_decision' => OrderReturn::query()->where('status', OrderReturn::STATUS_REQUESTED)->count(),
                'invoice_review' => (clone $approved)->where('invoice_status', OrderReturn::INVOICE_PENDING)->count(),
                'ready_to_refund' => (clone $approved)
                    ->where(fn ($q) => $q->where('invoice_required', false)->orWhere('invoice_status', OrderReturn::INVOICE_ACCEPTED))
                    ->count(),
                'refunded_this_month' => (float) OrderReturn::query()
                    ->where('status', OrderReturn::STATUS_REFUNDED)
                    ->where('refunded_at', '>=', now()->startOfMonth())
                    ->sum('approved_amount'),
            ],
            'filters' => $filters,
            'statusLabels' => OrderReturn::statuses(),
            'invoiceStatusLabels' => OrderReturn::invoiceStatuses(),
        ]);
    }

    public function show(OrderReturn $orderReturn): Response
    {
        $orderReturn->load(['order', 'decider:id,name', 'invoiceReviewer:id,name', 'refunder:id,name']);
        $order = $orderReturn->order;
        $tenant = $this->returns->tenantOf($orderReturn);

        return Inertia::render('Bagisto::OrderReturnDetail', [
            'orderReturn' => [
                'id' => $orderReturn->id,
                'rma_id' => $orderReturn->bagisto_rma_id,
                'bagisto_order_id' => $orderReturn->bagisto_order_id,
                'status' => $orderReturn->status,
                'bagisto_status' => $orderReturn->bagisto_status,
                'reason' => $orderReturn->reason,
                'resolution' => $orderReturn->resolution,
                'information' => $orderReturn->information,
                'cargo_method' => $orderReturn->cargo_method,
                'items' => $orderReturn->items ?? [],
                'images' => $orderReturn->images ?? [],
                'inspection' => $orderReturn->inspection,
                'requested_amount' => (float) $orderReturn->requested_amount,
                'approved_amount' => $orderReturn->approved_amount !== null ? (float) $orderReturn->approved_amount : null,
                'decision_note' => $orderReturn->decision_note,
                'decided_by' => $orderReturn->decider?->name,
                'decided_at' => optional($orderReturn->decided_at)->toIso8601String(),
                'invoice_required' => $orderReturn->invoice_required,
                'invoice_status' => $orderReturn->invoice_status,
                'invoice_no' => $orderReturn->invoice_no,
                'invoice_date' => optional($orderReturn->invoice_date)->toDateString(),
                'invoice_amount' => $orderReturn->invoice_amount !== null ? (float) $orderReturn->invoice_amount : null,
                'invoice_has_file' => filled($orderReturn->invoice_path),
                'invoice_uploaded_at' => optional($orderReturn->invoice_uploaded_at)->toIso8601String(),
                'invoice_review_note' => $orderReturn->invoice_review_note,
                'invoice_reviewed_by' => $orderReturn->invoiceReviewer?->name,
                'refund_method' => $orderReturn->refund_method,
                'refund_reference' => $orderReturn->refund_reference,
                'refunded_by' => $orderReturn->refunder?->name,
                'refunded_at' => optional($orderReturn->refunded_at)->toIso8601String(),
                'created_at' => optional($orderReturn->bagisto_created_at ?? $orderReturn->created_at)->toIso8601String(),
                'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'code' => $tenant->code] : null,
                'order' => $order ? [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'total' => (float) $order->total,
                    'payment_method' => $order->payment_method,
                    'status' => $order->status,
                ] : null,
            ],
            'refundableCap' => $this->returns->refundableCap($orderReturn),
            'refundMethod' => $orderReturn->status === OrderReturn::STATUS_APPROVED ? $this->returns->refundMethodFor($orderReturn) : $orderReturn->refund_method,
            'orderInvoiced' => $order ? $this->returns->orderIsInvoiced($order->id) : null,
            'statusLabels' => OrderReturn::statuses(),
            'invoiceStatusLabels' => OrderReturn::invoiceStatuses(),
            'refundMethodLabels' => OrderReturn::refundMethods(),
        ]);
    }

    public function approve(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->run(fn () => $this->returns->approve($orderReturn, (float) $data['amount'], $data['note'] ?? null, $request->user()), 'İade onaylandı, bayiye bildirildi.');
    }

    public function reject(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        return $this->run(fn () => $this->returns->reject($orderReturn, $data['note'], $request->user()), 'İade talebi reddedildi, bayiye bildirildi.');
    }

    public function reviewInvoice(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $data = $request->validate([
            'accept' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->run(
            fn () => $this->returns->reviewInvoice($orderReturn, (bool) $data['accept'], $data['note'] ?? null, $request->user()),
            $data['accept'] ? 'İade faturası kabul edildi.' : 'İade faturası reddedildi, bayiden yenisi istendi.',
        );
    }

    public function refund(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:191'],
        ]);

        return $this->run(fn () => $this->returns->refund($orderReturn, $request->user(), $data['reference'] ?? null), 'İade ödemesi yapıldı.');
    }

    public function invoice(OrderReturn $orderReturn): StreamedResponse
    {
        abort_unless($orderReturn->invoice_path && Storage::disk('local')->exists($orderReturn->invoice_path), 404);

        $extension = pathinfo($orderReturn->invoice_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $orderReturn->invoice_path,
            'iade-faturasi-'.($orderReturn->invoice_no ?: $orderReturn->id).'.'.$extension,
        );
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OrderReturnException $e) {
            // withErrors: Inertia onError tetiklensin, modal açık kalsın (bkz. OrderController::updateStatus).
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
