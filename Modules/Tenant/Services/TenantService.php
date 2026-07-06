<?php

namespace Modules\Tenant\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Models\TenantPriceList;

class TenantService
{
    public function __construct(private TenantCreditService $credit) {}

    public function create(array $data): Tenant
    {
        $data['slug']        = $data['slug']        ?? Str::slug($data['name']);
        $data['created_by']  = $data['created_by']  ?? auth()->id();
        // Phase 4 XML feed token; rotate edilebilir.
        $data['feed_secret'] = $data['feed_secret'] ?? Str::random(64);

        return Tenant::create($data);
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tenant->update($data);

        return $tenant->fresh();
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->update([
            'is_active' => false,
        ]);
    }

    public function activate(Tenant $tenant): void
    {
        $tenant->update([
            'is_active'    => true,
            'activated_at' => now(),
        ]);
    }

    public function getActiveTenants(): Collection
    {
        return Tenant::where('is_active', true)->with('type')->get();
    }

    public function getPriceListForTenant(Tenant $tenant): Collection
    {
        return $tenant->priceLists()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->get();
    }

    public function addPriceList(Tenant $tenant, array $data): TenantPriceList
    {
        return $tenant->priceLists()->create($data);
    }

    public function createInvoice(Tenant $tenant, array $data): TenantInvoice
    {
        return $tenant->invoices()->create($data);
    }

    public function markInvoicePaid(TenantInvoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Fatura ödendi → tenant'ın borcu o kadar düşer.
        $this->credit->credit(
            tenant: $invoice->tenant,
            amount: (float) $invoice->amount,
            reason: 'invoice_paid',
            invoiceId: $invoice->id,
        );
    }
}
