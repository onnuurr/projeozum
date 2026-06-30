<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tenant\Models\Tenant;

class Order extends Model
{
    public const TYPE_B2C      = 'b2c';
    public const TYPE_DROPSHIP = 'dropship';

    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'tenant_id',
        'shipping_info',
        'payment_method',
        'note',
        'subtotal',
        'shipping_fee',
        'total',
        'status',
        'order_type',
    ];

    protected $casts = [
        'shipping_info' => 'array',
        'subtotal'      => 'decimal:2',
        'shipping_fee'  => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeDropship(Builder $query): Builder
    {
        return $query->where('order_type', self::TYPE_DROPSHIP);
    }
}
