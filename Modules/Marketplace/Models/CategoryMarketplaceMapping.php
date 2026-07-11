<?php

namespace Modules\Marketplace\Models;

use Modules\Product\Models\Category;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMarketplaceMapping extends Model
{
    protected $table = 'category_marketplace_mappings';

    protected $fillable = [
        'category_id',
        'marketplace_id',
        'category_path',
        'external_id',
        'synced_products',
        'last_synced_at',
    ];

    protected $casts = [
        'synced_products' => 'integer',
        'last_synced_at'  => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }
}
