<?php

namespace Modules\Marketplace\Models;

use Modules\Product\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMarketplaceListing extends Model
{
    protected $table = 'product_marketplace_listings';

    protected $fillable = [
        'product_id',
        'marketplace_id',
        'is_sent',
        'sent_at',
        'product_status',
        'approval_status',
        'store_name',
        'model_code',
        'category_path',
        'title',
        'price',
        'currency',
        'variant_extra_price',
        'delivery_template',
        'shipping_time',
        'variants',
    ];

    protected $casts = [
        'is_sent'             => 'boolean',
        'sent_at'             => 'datetime',
        'price'               => 'decimal:2',
        'variant_extra_price' => 'decimal:2',
        'shipping_time'       => 'integer',
        'variants'            => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }
}
