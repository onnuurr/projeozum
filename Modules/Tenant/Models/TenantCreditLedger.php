<?php

namespace Modules\Tenant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Order;

class TenantCreditLedger extends Model
{
    public const TYPE_DEBIT  = 'debit';
    public const TYPE_CREDIT = 'credit';

    public const UPDATED_AT = null;

    protected $table = 'tenant_credit_ledger';

    protected $fillable = [
        'tenant_id',
        'type',
        'amount',
        'reason',
        'order_id',
        'invoice_id',
        'balance_after',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at'    => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
