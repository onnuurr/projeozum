<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aşamanın iç/fason + fasoncu + adet fiyatı kararının bir sürümü (iz). */
class ProductionStepDecision extends Model
{
    protected $table = 'production_step_decisions';

    protected $fillable = [
        'production_order_step_id', 'location_type', 'fason_supplier_id', 'unit_cost', 'note', 'decided_by',
    ];

    protected $casts = ['unit_cost' => 'decimal:2'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderStep::class, 'production_order_step_id');
    }

    public function fasonSupplier(): BelongsTo
    {
        return $this->belongsTo(FasonSupplier::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
