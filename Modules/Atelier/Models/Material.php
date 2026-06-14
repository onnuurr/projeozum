<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use SoftDeletes;

    protected $table = 'materials';

    protected $fillable = [
        'code', 'name', 'type', 'unit', 'unit_cost', 'current_stock', 'is_active',
    ];

    protected $casts = [
        'unit_cost'     => 'decimal:2',
        'current_stock' => 'decimal:3',
        'is_active'     => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }
}
