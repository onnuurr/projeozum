<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceExpense extends Model
{
    public const TYPE_COMMISSION = 'commission';
    public const TYPE_SHIPPING   = 'shipping';
    public const TYPE_RETURN     = 'return';
    public const TYPE_ADS        = 'ads';
    public const TYPE_OTHER      = 'other';

    protected $table = 'marketplace_expenses';

    protected $fillable = [
        'tenant_id',
        'marketplace',
        'expense_type',
        'marketplace_sale_id',
        'amount',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSale::class, 'marketplace_sale_id');
    }
}
