<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'shipping_info',
        'payment_method',
        'note',
        'subtotal',
        'shipping_fee',
        'total',
        'status',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
