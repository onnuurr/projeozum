<?php

namespace Modules\Marketplace\Models;

use Modules\Tenant\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class MarketplaceSale extends Model
{
    public const STATUS_NEW       = 'new';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED  = 'returned';

    protected $table = 'marketplace_sales';

    protected $fillable = [
        'tenant_id',
        'marketplace',
        'external_order_id',
        'external_line_id',
        'product_id',
        'sold_price',
        'qty',
        'commission',
        'shipping_fee',
        'net_revenue',
        'status',
        'raw_payload',
        'sold_at',
        'synced_at',
    ];

    protected $casts = [
        'sold_price'   => 'decimal:2',
        'qty'          => 'integer',
        'commission'   => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'net_revenue'  => 'decimal:2',
        'raw_payload'  => 'array',
        'sold_at'      => 'datetime',
        'synced_at'    => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(MarketplaceExpense::class);
    }
}
