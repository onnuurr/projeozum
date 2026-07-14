<?php

namespace Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Category;

class MarketplaceCommissionRate extends Model
{
    protected $table = 'marketplace_commission_rates';

    protected $fillable = [
        'marketplace',
        'category_id',
        'commission_rate',
        'shipping_rate',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'shipping_rate'   => 'decimal:2',
        'valid_from'      => 'date',
        'valid_until'     => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
