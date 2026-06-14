<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    protected $table = 'operations';

    protected $fillable = ['code', 'name', 'default_location', 'default_unit_cost', 'sort_order'];

    protected $casts = [
        'default_unit_cost' => 'decimal:2',
        'sort_order'        => 'integer',
    ];
}
