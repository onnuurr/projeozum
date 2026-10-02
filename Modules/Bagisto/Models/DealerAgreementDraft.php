<?php

namespace Modules\Bagisto\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir sözleşmenin bir dildeki yayımlanmamış taslağı (sözleşme + dil başına tek kayıt, yayında silinir).
 * Düşük hacimli çalışma kaydı — Prunable gerekmiyor.
 */
class DealerAgreementDraft extends Model
{
    protected $fillable = ['dealer_agreement_id', 'locale', 'title', 'body', 'format', 'change_note', 'updated_by'];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(DealerAgreement::class, 'dealer_agreement_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
