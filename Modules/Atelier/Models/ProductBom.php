<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class ProductBom extends Model
{
    protected $table = 'product_boms';

    protected $fillable = ['product_id', 'name', 'extra_cost_per_unit', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'extra_cost_per_unit' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class, 'bom_id');
    }
}
