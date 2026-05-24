<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantMarketplaceCredential extends Model
{
    use SoftDeletes;

    public const MARKETPLACE_TRENDYOL    = 'trendyol';
    public const MARKETPLACE_HEPSIBURADA = 'hepsiburada';
    public const MARKETPLACE_N11         = 'n11';
    public const MARKETPLACE_CICEKSEPETI = 'ciceksepeti';

    public const MARKETPLACES = [
        self::MARKETPLACE_TRENDYOL,
        self::MARKETPLACE_HEPSIBURADA,
        self::MARKETPLACE_N11,
        self::MARKETPLACE_CICEKSEPETI,
    ];

    public const LABELS = [
        self::MARKETPLACE_TRENDYOL    => 'Trendyol',
        self::MARKETPLACE_HEPSIBURADA => 'Hepsiburada',
        self::MARKETPLACE_N11         => 'N11',
        self::MARKETPLACE_CICEKSEPETI => 'Çiçeksepeti',
    ];

    protected $table = 'tenant_marketplace_credentials';

    protected $fillable = [
        'tenant_id',
        'marketplace',
        'supplier_id',
        'store_name',
        'api_key',
        'api_secret',
        'is_active',
        'last_sync_at',
        'last_error',
        'notes',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_sync_at' => 'datetime',
        'api_key'      => 'encrypted',
        'api_secret'   => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getMarketplaceLabelAttribute(): string
    {
        return self::LABELS[$this->marketplace] ?? $this->marketplace;
    }
}
