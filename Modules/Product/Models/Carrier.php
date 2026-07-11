<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kargo firması (Aras, Yurtiçi, MNG...). Superadmin yönetir (carrier.manage).
 *
 * Düşük hacimli iş varlığı → soft-delete geçmiş sipariş FK'lerini korumak için
 * var, ancak Prunable YOK (Tenant/TenantType ile aynı bilinçli karar).
 */
class Carrier extends Model
{
    use SoftDeletes;

    protected $table = 'carriers';

    protected $fillable = [
        'code',
        'name',
        'tracking_url_template',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
