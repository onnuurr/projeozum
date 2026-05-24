<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantType extends Model
{
    use SoftDeletes;

    public const PRICE_RETAIL   = 'retail';
    public const PRICE_DEALER   = 'dealer';
    public const PRICE_DROPSHIP = 'dropship';

    protected $table = 'tenant_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_list_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
