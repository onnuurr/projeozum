<?php

namespace Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketplace extends Model
{
    protected $table = 'marketplaces';

    protected $fillable = [
        'key',
        'name',
        'logo_text',
        'color',
        'connected',
        'sort_order',
    ];

    protected $casts = [
        'connected'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(CategoryMarketplaceMapping::class);
    }
}
