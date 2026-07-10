<?php

namespace Modules\Product\Models;

use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;
    use Prunable;
    use SoftDeletes;

    protected static function newFactory(): PriceListFactory
    {
        return PriceListFactory::new();
    }

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 30;

    public const TYPE_RETAIL   = 'retail';
    public const TYPE_DEALER   = 'dealer';
    public const TYPE_DROPSHIP = 'dropship';

    protected $table = 'price_lists';

    protected $fillable = [
        'product_variant_id',
        'type',
        'price',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // Retail fiyatı kaydedildiğinde variants.price ile senkron tutulur (karar #5).
    protected static function booted(): void
    {
        static::saved(function (PriceList $priceList): void {
            if ($priceList->type === self::TYPE_RETAIL && $priceList->is_active) {
                $priceList->variant?->update(['price' => $priceList->price]);
            }
        });
    }

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
