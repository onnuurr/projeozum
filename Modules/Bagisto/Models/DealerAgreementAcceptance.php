<?php

namespace Modules\Bagisto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayinin onayladığı sözleşmenin, onay anındaki doldurulmuş kopyası (bkz. DealerAgreementService::recordAcceptance).
 * Yalnızca eklenir; hukuki kayıt olduğu için Prunable bilerek yok.
 */
class DealerAgreementAcceptance extends Model
{
    public const CONTEXT_APPLICATION = 'application';

    public const CONTEXT_OFFICIAL = 'official';

    public const CONTEXT_MEMBERSHIP = 'membership';

    /** Bayi portalına girişte istenen yeniden onay (yeni ek sözleşme / "yeniden onay iste" ile yayımlanan sürüm). */
    public const CONTEXT_RENEWAL = 'renewal';

    public const CONTEXT_LABELS = [
        self::CONTEXT_APPLICATION => 'Ön başvuru',
        self::CONTEXT_OFFICIAL => 'Resmi belge adımı',
        self::CONTEXT_MEMBERSHIP => 'Üyelik tamamlama',
        self::CONTEXT_RENEWAL => 'Girişte yeniden onay',
    ];

    protected $fillable = [
        'dealer_agreement_version_id', 'tenant_application_id', 'tenant_id', 'agreement_key', 'version',
        'locale', 'context', 'title', 'html', 'data', 'ip_address', 'accepted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function agreementVersion(): BelongsTo
    {
        return $this->belongsTo(DealerAgreementVersion::class, 'dealer_agreement_version_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(TenantApplication::class, 'tenant_application_id');
    }
}
