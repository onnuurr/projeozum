<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İş emrinin hammadde rezervi + tüketimi. Açık rezerv = required − consumed (released_at boşken). */
class ProductionOrderMaterial extends Model
{
    protected $table = 'production_order_materials';

    protected $fillable = [
        'production_order_id', 'material_id', 'per_unit_qty', 'required_qty', 'consumed_qty',
        'unit_cost', 'released_at',
    ];

    protected $casts = [
        'per_unit_qty' => 'decimal:4',
        'required_qty' => 'decimal:3',
        'consumed_qty' => 'decimal:3',
        'unit_cost'    => 'decimal:2',
        'released_at'  => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function openQty(): float
    {
        return $this->released_at ? 0.0 : max(0.0, (float) $this->required_qty - (float) $this->consumed_qty);
    }
}
