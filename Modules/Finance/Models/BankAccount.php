<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $table = 'finance_bank_accounts';

    protected $fillable = [
        'bank_name',
        'account_name',
        'iban',
        'currency',
        'account_number',
        'is_active',
        'note',
    ];

    protected $attributes = [
        'currency'  => 'TRY',
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function statementImports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
