<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandKit extends Model
{
    use SoftDeletes;

    protected $table = 'brand_kits';

    protected $fillable = [
        'name',
        'is_default',
        'palette',
        'typography',
        'logos',
        'spacing',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'palette'    => 'array',
        'typography' => 'array',
        'logos'      => 'array',
        'spacing'    => 'array',
    ];

    /**
     * Varsayılan kit; yoksa en son oluşturulan kit; hiç kit yoksa null.
     */
    public static function resolveDefault(): ?self
    {
        return static::query()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
