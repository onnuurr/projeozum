<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $table = 'bom_lines';

    protected $fillable = ['bom_id', 'material_id', 'quantity_per_unit', 'waste_pct'];

    protected $casts = [
        'quantity_per_unit' => 'decimal:4',
        'waste_pct'         => 'decimal:2',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductBom::class, 'bom_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
