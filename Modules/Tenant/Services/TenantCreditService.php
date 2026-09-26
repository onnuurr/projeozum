<?php

namespace Modules\Tenant\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;

/**
 * Tenant kredi/balance mutasyonlarının TEK noktası.
 *
 * Davranış:
 *  - charge() siparişte, current_balance += amount; available_credit < amount ise hard-block.
 *  - credit() fatura paid'de, current_balance -= amount (negatif balance OK — peşin ödeme avansı).
 *  - Her mutasyon DB transaction + lockForUpdate ile serileştirilir; ledger satırı atomik yazılır.
 */
class TenantCreditService
{
    public function assertCanCharge(Tenant $tenant, float $amount): void
    {
        $available = $this->availableCreditFor($tenant);
        if ($amount > $available) {
            throw new InsufficientCreditException($tenant, $amount, $available);
        }
    }

    public function charge(
        Tenant $tenant,
        float $amount,
        string $reason,
        ?int $orderId = null,
        ?int $invoiceId = null,
        ?int $byUserId = null,
    ): TenantCreditLedger {
        return DB::transaction(function () use ($tenant, $amount, $reason, $orderId, $invoiceId, $byUserId) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            $available = (float) $locked->credit_limit - (float) $locked->current_balance;
            if ($amount > $available) {
                throw new InsufficientCreditException($locked, $amount, $available);
            }

            $newBalance = (float) $locked->current_balance + $amount;
            $locked->forceFill(['current_balance' => $newBalance])->save();

            return TenantCreditLedger::create([
                'tenant_id'     => $locked->id,
                'type'          => TenantCreditLedger::TYPE_DEBIT,
                'amount'        => $amount,
                'reason'        => $reason,
                'order_id'      => $orderId,
                'invoice_id'    => $invoiceId,
                'balance_after' => $newBalance,
                'created_by'    => $byUserId ?? Auth::id(),
            ]);
        });
    }

    public function credit(
        Tenant $tenant,
        float $amount,
        string $reason,
        ?int $orderId = null,
        ?int $invoiceId = null,
        ?int $byUserId = null,
    ): TenantCreditLedger {
        return DB::transaction(function () use ($tenant, $amount, $reason, $orderId, $invoiceId, $byUserId) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            $newBalance = (float) $locked->current_balance - $amount;
            $locked->forceFill(['current_balance' => $newBalance])->save();

            return TenantCreditLedger::create([
                'tenant_id'     => $locked->id,
                'type'          => TenantCreditLedger::TYPE_CREDIT,
                'amount'        => $amount,
                'reason'        => $reason,
                'order_id'      => $orderId,
                'invoice_id'    => $invoiceId,
                'balance_after' => $newBalance,
                'created_by'    => $byUserId ?? Auth::id(),
            ]);
        });
    }

    /**
     * Bir sipariş için cariden net çekilen tutar (borç − iade). Kartla peşin ödenen ya da
     * kredi yetersizliğinden çekilemeyen siparişte 0 döner; iptal iadesi buna göre yapılır.
     */
    public function netChargedForOrder(Tenant $tenant, int $orderId): float
    {
        $ledger = TenantCreditLedger::where('tenant_id', $tenant->id)->where('order_id', $orderId);

        $debits = (float) (clone $ledger)->where('type', TenantCreditLedger::TYPE_DEBIT)->sum('amount');
        $credits = (float) (clone $ledger)->where('type', TenantCreditLedger::TYPE_CREDIT)->sum('amount');

        return round($debits - $credits, 2);
    }

    public function availableCreditFor(Tenant $tenant): float
    {
        $fresh = $tenant->fresh();

        return (float) $fresh->credit_limit - (float) $fresh->current_balance;
    }
}
