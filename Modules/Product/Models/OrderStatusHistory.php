<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sipariş durum geçiş denetim kaydı (D3). Kalıcı iş denetimi — Prunable DEĞİL.
 */
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_histories';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'user_id',
        'note',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
