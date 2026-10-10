<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ürün formundaki beden seçim listesi. Varyant bedeni product_variants.size'da düz metin
 * olarak tutulur; yeniden adlandırma varyantlara da yansır, silme dokunmaz (VariantOptionService).
 * Bagisto'daki `size` seçeneklerinin ana kaynağıdır (PushAttributeOptionToBagisto).
 */
class ProductSize extends Model
{
    protected $table = 'product_sizes';

    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
