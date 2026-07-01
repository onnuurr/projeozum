<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\TenantInvoice;

class ProformaInvoice extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'finance_proforma_invoices';

    protected $fillable = [
        'proforma_no',
        'order_id',
        'tenant_invoice_id',
        'buyer_name',
        'buyer_tax_number',
        'issue_date',
        'valid_until',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'converted_invoice_id',
        'note',
        'created_by',
    ];

    protected $attributes = [
        'status'   => self::STATUS_DRAFT,
        'currency' => 'TRY',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'valid_until' => 'date',
        'subtotal'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenantInvoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(OutgoingInvoice::class, 'converted_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
