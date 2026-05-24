<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAccessRule extends Model
{
    public const SCOPE_BRAND    = 'brand';
    public const SCOPE_CATEGORY = 'category';

    protected $table = 'tenant_access_rules';

    protected $fillable = [
        'tenant_id',
        'scope_type',
        'scope_id',
        'is_blocked',
        'notes',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'scope_id'   => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
