<?php

namespace Modules\Product\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
