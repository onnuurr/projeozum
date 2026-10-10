<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ürün formundaki renk seçim listesi. Varyant rengi product_variants.color_name/color_hex'te
 * düz metin olarak tutulur; ad/hex değişikliği varyantlara da yansır, silme dokunmaz
 * (VariantOptionService). Bagisto'daki `color` seçeneklerinin ana kaynağıdır (PushAttributeOptionToBagisto).
 */
class ProductColor extends Model
{
    protected $table = 'product_colors';

    protected $fillable = [
        'name',
        'hex',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
