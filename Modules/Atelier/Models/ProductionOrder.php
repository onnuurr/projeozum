<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;

class ProductionOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_PLANNED     = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $table = 'production_orders';

    protected $fillable = [
        'code', 'product_id', 'warehouse_id', 'status', 'planned_qty', 'received_qty',
        'planned_start', 'due_date', 'material_cost', 'fason_cost', 'labor_cost',
        'total_cost', 'unit_cost', 'closed_at', 'closed_by', 'notes', 'created_by',
    ];

    protected $casts = [
        'planned_qty'   => 'integer',
        'received_qty'  => 'integer',
        'planned_start' => 'date',
        'due_date'      => 'date',
        'material_cost' => 'decimal:2',
        'fason_cost'    => 'decimal:2',
        'labor_cost'    => 'decimal:2',
        'total_cost'    => 'decimal:2',
        'unit_cost'     => 'decimal:2',
        'closed_at'     => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProductionOrderStep::class)->orderBy('sequence');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PLANNED, self::STATUS_IN_PROGRESS], true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
