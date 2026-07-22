<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün durum geçiş denetim kaydı (Faz 2). Kalıcı iş denetimi — Prunable DEĞİL.
 */
class ProductStatusHistory extends Model
{
    protected $table = 'product_status_histories';

    protected $fillable = [
        'product_id',
        'from_status',
        'to_status',
        'user_id',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
