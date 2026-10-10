<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seçimli/çoklu seçimli ürün özelliğinin seçeneği. `icon`: ortak ikon kaydındaki ad (isteğe bağlı). Ürünler seçeneği id ile tutar; Bagisto'ya
 * ad ile eşlenir (yeniden adlandırmada eski ad da gider — PushAttributeOptionToBagisto).
 */
class ProductAttributeOption extends Model
{
    protected $table = 'product_attribute_options';

    protected $fillable = [
        'product_attribute_id',
        'name',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}
