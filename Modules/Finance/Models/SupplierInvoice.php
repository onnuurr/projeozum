<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Atelier\Models\ProductionOrder;

class SupplierInvoice extends Model
{
    use SoftDeletes;

    public const STATUS_UNPAID          = 'unpaid';
    public const STATUS_PARTIALLY_PAID  = 'partially_paid';
    public const STATUS_PAID            = 'paid';
    public const STATUS_CANCELLED       = 'cancelled';

    protected $table = 'finance_supplier_invoices';

    protected $fillable = [
        'invoice_no',
        'supplier_name',
        'supplier_tax_number',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'paid_at',
        'category',
        'production_order_id',
        'file_path',
        'note',
        'created_by',
    ];

    protected $attributes = [
        'status'   => self::STATUS_UNPAID,
        'currency' => 'TRY',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total'        => 'decimal:2',
        'paid_at'      => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
