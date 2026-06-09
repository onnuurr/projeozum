<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 30;

    protected $table = 'stocks';

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'min_quantity',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'reserved_quantity' => 'integer',
        'min_quantity'      => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Minimum stok seviyesi altındaki kayıtları filtreler
    public function scopeBelowMin(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_quantity');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
