<?php

namespace Modules\Tenant\Models;

use Database\Factories\TenantInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvoice extends Model
{
    /** @use HasFactory<TenantInvoiceFactory> */
    use HasFactory;

    protected static function newFactory(): TenantInvoiceFactory
    {
        return TenantInvoiceFactory::new();
    }
    protected $fillable = [
        'tenant_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'note',
    ];

    protected $attributes = [
        'status'   => 'pending',
        'currency' => 'TRY',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
