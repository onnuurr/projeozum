<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerBrandKit extends Model
{
    use SoftDeletes;

    protected $table = 'partner_brand_kits';

    protected $fillable = [
        'name',
        'is_active',
        'logo_path',
        'contact',
        'cta_phrases',
        'price_display',
        'notes',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'contact'       => 'array',
        'cta_phrases'   => 'array',
        'price_display' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
