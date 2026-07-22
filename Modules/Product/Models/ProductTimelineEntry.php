<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün domain event denetim kaydı (Faz 1). Kalıcı iş denetimi — Prunable DEĞİL.
 */
class ProductTimelineEntry extends Model
{
    protected $table = 'product_timeline_events';

    protected $fillable = [
        'product_id',
        'type',
        'payload',
        'user_id',
    ];

    protected $casts = [
        'payload' => 'array',
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
