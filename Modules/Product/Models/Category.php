<?php

namespace Modules\Product\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'manually_passive', // kullanıcı bilerek pasife aldı; otomatik aktifleştirme ezmez (CategoryStatusService)
        'vat_rate',         // KDV %; boş = üst kategoriden miras (VatRateService)
        'sort_order',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order' => 'integer',
        'manually_passive' => 'boolean',
        'vat_rate'         => 'decimal:2',
    ];

    /** Aynı üst kategorinin (kardeşlerin) sonuna eklenecek sıra numarası. */
    public static function nextSortOrder(?int $parentId): int
    {
        return (int) static::query()
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
            ->max('sort_order') + 1;
    }

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

    /** Bu kategoride (ve alt kategorilerinde) ürün formunda görünen global özellikler. */
    public function productAttributes(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttribute::class, 'category_product_attribute')->withPivot('required')->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
