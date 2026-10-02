<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class ProductionOrderItem extends Model
{
    protected $table = 'production_order_items';

    protected $fillable = [
        'production_order_id', 'product_variant_id', 'planned_qty', 'received_qty',
    ];

    protected $casts = [
        'planned_qty'  => 'integer',
        'received_qty' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
