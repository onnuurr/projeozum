<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Atelier\Models\Material;

class ProductDescriptionMaterial extends Model
{
    protected $table = 'product_description_materials';

    public const ROLE_PRIMARY_FABRIC = 'primary_fabric';
    public const ROLE_SECONDARY      = 'secondary';
    public const ROLE_TRIM           = 'trim';
    public const ROLE_ACCESSORY      = 'accessory';
    public const ROLE_LABEL          = 'label';

    public const ROLES = [
        self::ROLE_PRIMARY_FABRIC,
        self::ROLE_SECONDARY,
        self::ROLE_TRIM,
        self::ROLE_ACCESSORY,
        self::ROLE_LABEL,
    ];

    protected $fillable = [
        'product_id',
        'material_id',
        'role',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
