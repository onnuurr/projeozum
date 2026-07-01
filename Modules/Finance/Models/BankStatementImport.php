<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    public const FORMAT_MT940 = 'mt940';
    public const FORMAT_CSV   = 'csv';
    public const FORMAT_XLSX  = 'xlsx';

    protected $table = 'finance_bank_statement_imports';

    protected $fillable = [
        'bank_account_id',
        'file_path',
        'original_filename',
        'format',
        'period_start',
        'period_end',
        'status',
        'imported_row_count',
        'error_message',
        'imported_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'period_start'       => 'date',
        'period_end'         => 'date',
        'imported_row_count' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
