<?php

namespace Modules\Bagisto\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir bayi sözleşmesinin yayımlanmış sürümü. Yalnızca eklenir, güncellenmez/silinmez: bayilerin
 * onayladığı sürüm numarası başvuru kaydında (details.consents.*_version) tutulur ve o metnin her zaman
 * geri okunabilmesi gerekir. Düşük hacimli hukuki kayıt — Prunable bilerek yok.
 */
class DealerAgreementVersion extends Model
{
    /** Zengin metin editörüyle yazılan sürüm (HTMLPurifier'dan geçmiş HTML). */
    public const FORMAT_HTML = 'html';

    /** 2026-10-02 öncesi sürümler. */
    public const FORMAT_MARKDOWN = 'markdown';

    protected $fillable = ['dealer_agreement_id', 'locale', 'version', 'title', 'body', 'format', 'requires_acceptance', 'variables_snapshot', 'change_note', 'published_by', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
        'requires_acceptance' => 'boolean',
        'variables_snapshot' => 'array',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(DealerAgreement::class, 'dealer_agreement_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
