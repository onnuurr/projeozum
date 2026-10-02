<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bir aşamadan çıkan parti: sağlam + fire adet. */
class ProductionStepBatch extends Model
{
    protected $table = 'production_step_batches';

    protected $fillable = ['production_order_step_id', 'good_qty', 'scrap_qty', 'note', 'created_by'];

    protected $casts = [
        'good_qty'  => 'integer',
        'scrap_qty' => 'integer',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderStep::class, 'production_order_step_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
