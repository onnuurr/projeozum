<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\TenantInvoice;

class OutgoingInvoice extends Model
{
    use SoftDeletes;

    public const TYPE_SALES_ORDER = 'sales_order';
    public const TYPE_TENANT_SALE = 'tenant_sale';
    public const TYPE_STANDALONE  = 'standalone';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_READY     = 'ready';
    public const STATUS_SENT      = 'sent';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const EFATURA_NOT_SENT = 'not_sent';
    public const EFATURA_PENDING  = 'pending';
    public const EFATURA_SUCCESS  = 'success';
    public const EFATURA_FAILED   = 'failed';

    protected $table = 'finance_outgoing_invoices';

    protected $fillable = [
        'invoice_no',
        'invoice_type',
        'order_id',
        'tenant_invoice_id',
        'buyer_name',
        'buyer_tax_number',
        'buyer_address',
        'issue_date',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'efatura_uuid',
        'efatura_provider',
        'efatura_status',
        'efatura_raw_response',
        'sent_at',
        'pdf_path',
        'converted_from_proforma_id',
        'note',
        'created_by',
    ];

    protected $attributes = [
        'invoice_type'   => self::TYPE_STANDALONE,
        'status'         => self::STATUS_DRAFT,
        'currency'       => 'TRY',
        'efatura_status' => self::EFATURA_NOT_SENT,
    ];

    protected $casts = [
        'issue_date'            => 'date',
        'subtotal'              => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',
        'efatura_raw_response'  => 'array',
        'sent_at'               => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OutgoingInvoiceItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenantInvoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class);
    }

    public function convertedFromProforma(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class, 'converted_from_proforma_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
