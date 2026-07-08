<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingInvoiceItem extends Model
{
    protected $table = 'finance_outgoing_invoice_items';

    protected $fillable = [
        'outgoing_invoice_id',
        'item_name',
        'unit_code',
        'quantity',
        'unit_price',
        'vat_rate',
        'taxable_amount',
        'vat_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'vat_rate'       => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'line_total'     => 'decimal:2',
    ];

    public function outgoingInvoice(): BelongsTo
    {
        return $this->belongsTo(OutgoingInvoice::class);
    }
}
