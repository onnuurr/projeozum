<?php

namespace Modules\Product\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    protected $table = 'product_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function marketplaceMappings(): HasMany
    {
        return $this->hasMany(CategoryMarketplaceMapping::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
