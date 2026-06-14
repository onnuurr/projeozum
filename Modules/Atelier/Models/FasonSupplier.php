<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FasonSupplier extends Model
{
    use SoftDeletes;

    protected $table = 'fason_suppliers';

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address', 'tax_no', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
