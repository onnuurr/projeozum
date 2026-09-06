<?php

namespace Modules\Tenant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantApplication extends Model
{
    protected $table = 'tenant_applications';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'legal_name',
        'tax_number',
        'tax_office',
        'contact_person',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'district',
        'country',
        'postal_code',
        'message',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'converted_tenant_id',
        'ip_address',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(TenantApplicationDocument::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }
}
