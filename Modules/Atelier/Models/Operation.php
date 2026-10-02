<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    protected $table = 'operations';

    protected $fillable = ['code', 'name', 'default_location', 'default_unit_cost', 'consumes_materials', 'sort_order'];

    protected $casts = [
        'default_unit_cost'  => 'decimal:2',
        'consumes_materials' => 'boolean',
        'sort_order'        => 'integer',
    ];
}
