<?php

namespace Modules\Tenant\Models;

use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'min_order_total',
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
        'min_order_total'   => 'decimal:2',
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

    /** Portal'a giriş yapan yetkili hesap — 'tenant' rolüne sahip ilk kullanıcı. */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id')->role('tenant');
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

    /**
     * withTrashed kullanılır çünkü slug unique index'i soft-delete'i hariç tutmuyor
     * (bkz. 2026_05_24_100000_add_plan_columns_to_tenants_table); silinmiş bir tenant'ın
     * slug'ı yeni kayıtlar için hâlâ rezerve.
     */
    public static function generateUniqueSlug(?string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name ?: 'tenant', '-', 'tr');
        if ($base === '') {
            $base = 'tenant';
        }

        $slug = $base;
        $i    = 2;
        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
