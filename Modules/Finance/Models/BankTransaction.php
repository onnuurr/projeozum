<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği (~2 yıl, log niteliğinde). */
    public const PRUNE_AFTER_DAYS = 730;

    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_MATCHED   = 'matched';
    public const STATUS_IGNORED   = 'ignored';

    public const MATCH_SUPPLIER_INVOICE = 'supplier_invoice';
    public const MATCH_OUTGOING_INVOICE = 'outgoing_invoice';
    public const MATCH_TENANT_INVOICE   = 'tenant_invoice';
    public const MATCH_ORDER            = 'order';

    protected $table = 'finance_bank_transactions';

    protected $fillable = [
        'bank_statement_import_id',
        'bank_account_id',
        'transaction_date',
        'value_date',
        'description',
        'reference',
        'amount',
        'currency',
        'balance_after',
        'reconciliation_status',
        'matched_invoice_type',
        'matched_invoice_id',
        'matched_at',
        'matched_by',
    ];

    protected $attributes = [
        'reconciliation_status' => self::STATUS_UNMATCHED,
        'currency'              => 'TRY',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date'       => 'date',
        'amount'           => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'matched_at'       => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
