<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderStep extends Model
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    protected $table = 'production_order_steps';

    protected $fillable = [
        'production_order_id', 'operation_id', 'sequence', 'location_type', 'fason_supplier_id',
        'status', 'input_qty', 'output_qty', 'scrap_qty', 'unit_cost', 'step_cost',
        'started_at', 'completed_at', 'note',
    ];

    protected $casts = [
        'sequence'     => 'integer',
        'input_qty'    => 'integer',
        'output_qty'   => 'integer',
        'scrap_qty'    => 'integer',
        'unit_cost'    => 'decimal:2',
        'step_cost'    => 'decimal:2',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
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
