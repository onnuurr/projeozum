<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

/** Ürün rotasının bir aşaması (operasyon + sıra + varsayılan iç/fason kararı). */
class ProductRouteStep extends Model
{
    protected $table = 'product_route_steps';

    protected $fillable = [
        'product_id', 'operation_id', 'sequence', 'location_type', 'fason_supplier_id', 'unit_cost',
    ];

    protected $casts = [
        'sequence'  => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function fasonSupplier(): BelongsTo
    {
        return $this->belongsTo(FasonSupplier::class);
    }
}
