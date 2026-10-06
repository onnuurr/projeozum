<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Category;

/**
 * Kategori → mokap bölgesi (bottom|top|one_piece|set) + varsayılan boy. Kaydı olmayan kategori
 * (üst kategorisi de yoksa) mokap dışıdır. Creative ayarlar sayfasından yönetilir.
 */
class CategoryRegion extends Model
{
    protected $table = 'creative_category_regions';

    protected $fillable = ['category_id', 'region', 'length'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
