<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialMovement extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 730; // ~2 yıl (log niteliğinde)

    public const TYPE_IN     = 'in';
    public const TYPE_OUT    = 'out';
    public const TYPE_ADJUST = 'adjust';

    protected $table = 'material_movements';

    protected $fillable = [
        'material_id', 'type', 'quantity', 'unit_cost', 'reason',
        'before_stock', 'after_stock', 'production_order_id', 'note', 'created_by',
    ];

    protected $casts = [
        'quantity'     => 'decimal:3',
        'unit_cost'    => 'decimal:2',
        'before_stock' => 'decimal:3',
        'after_stock'  => 'decimal:3',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
