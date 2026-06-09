<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 30;

    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'url',
        'alt_text',
        'sort_order',
        'is_cover',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_cover'   => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
