<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategoriye göre değişen ürün özelliği tanımı (Faz 3). `Product.attributes`
 * jsonb kolonunun hangi anahtarları kabul ettiğini belirler.
 */
class CategoryAttributeDefinition extends Model
{
    protected $table = 'category_attribute_definitions';

    protected $fillable = [
        'category_id',
        'key',
        'label',
        'type',
        'options',
        'required',
        'sort_order',
    ];

    protected $casts = [
        'options'    => 'array',
        'required'   => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
