<?php

namespace Modules\Tenant\Models;

use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected $table = 'tenants';

    protected $fillable = [
        'code',
        'name',
        'slug',
        'legal_name',
        'tenant_type_id',
        'tax_number',
        'tax_office',
        'email',
        'phone',
        'contact_person',
        'contact_phone',
        'address',
        'city',
        'district',
        'country',
        'postal_code',
        'logo_path',
        'settings',
        'feed_secret',
        'created_by',
        'credit_limit',
        'current_balance',
        'payment_term_days',
        'discount_rate',
        'is_active',
        'activated_at',
        'notes',
    ];

    protected $hidden = [
        'feed_secret',
    ];

    protected $casts = [
        'credit_limit'      => 'decimal:2',
        'current_balance'   => 'decimal:2',
        'discount_rate'     => 'decimal:2',
        'payment_term_days' => 'integer',
        'is_active'         => 'boolean',
        'activated_at'      => 'datetime',
        'settings'          => 'json',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TenantType::class, 'tenant_type_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(TenantAccessRule::class);
    }

    public function productAccess(): HasMany
    {
        return $this->hasMany(TenantProductAccess::class);
    }

    public function marketplaceCredentials(): HasMany
    {
        return $this->hasMany(TenantMarketplaceCredential::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(TenantPriceList::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class);
    }

    public function creditLedger(): HasMany
    {
        return $this->hasMany(TenantCreditLedger::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAvailableCreditAttribute(): float
    {
        return (float) $this->credit_limit - (float) $this->current_balance;
    }
}
