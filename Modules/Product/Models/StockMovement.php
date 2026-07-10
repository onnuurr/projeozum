<?php

namespace Modules\Product\Models;

use App\Models\User;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;
    use Prunable;
    use SoftDeletes;

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 30;

    public const TYPE_IN         = 'in';
    public const TYPE_OUT        = 'out';
    public const TYPE_TRANSFER   = 'transfer';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'stock_movements';

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'before_quantity' => 'integer',
        'after_quantity'  => 'integer',
        'reference_id'    => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
