<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoiceItem extends Model
{
    protected $table = 'finance_proforma_invoice_items';

    protected $fillable = [
        'proforma_invoice_id',
        'description',
        'qty',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'qty'         => 'integer',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }
}
