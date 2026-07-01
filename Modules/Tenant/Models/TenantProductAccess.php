<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class TenantProductAccess extends Model
{
    protected $table = 'tenant_product_access';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'is_blocked',
        'custom_price',
        'notes',
        'custom_name',
        'custom_description',
    ];

    protected $casts = [
        'is_blocked'   => 'boolean',
        'custom_price' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
