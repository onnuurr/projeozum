<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreativeTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'creative_templates';

    protected $fillable = [
        'name',
        'svg_path',
        'width',
        'height',
        'slots',
        'thumbnail_path',
        'is_active',
    ];

    protected $casts = [
        'width'     => 'integer',
        'height'    => 'integer',
        'slots'     => 'array',
        'is_active' => 'boolean',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class, 'template_id');
    }
}
