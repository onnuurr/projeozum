<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\ProductDescriptionMaterial;

class Material extends Model
{
    use SoftDeletes;

    protected $table = 'materials';

    protected $fillable = [
        'code', 'name', 'type', 'unit', 'unit_cost', 'current_stock', 'is_active', 'specs',
    ];

    protected $casts = [
        'unit_cost'     => 'decimal:2',
        'current_stock' => 'decimal:3',
        'is_active'     => 'boolean',
        'specs'         => 'array',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }

    public function descriptionMaterials(): HasMany
    {
        return $this->hasMany(ProductDescriptionMaterial::class);
    }
}
