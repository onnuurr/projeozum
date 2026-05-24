<?php

namespace Modules\Tenant\Models;

use Database\Factories\TenantPriceListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPriceList extends Model
{
    /** @use HasFactory<TenantPriceListFactory> */
    use HasFactory;

    protected static function newFactory(): TenantPriceListFactory
    {
        return TenantPriceListFactory::new();
    }
    protected $fillable = [
        'tenant_id',
        'product_group_id',
        'discount_rate',
        'special_price',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'special_price' => 'decimal:2',
        'valid_from'    => 'date',
        'valid_until'   => 'date',
        'is_active'     => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
