<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApplicationDocument extends Model
{
    protected $table = 'tenant_application_documents';

    public const TYPE_TAX_CERTIFICATE       = 'vergi_levhasi';
    public const TYPE_TRADE_REGISTRY_GAZETTE = 'ticaret_sicil_gazetesi';
    public const TYPE_SIGNATURE_CIRCULAR    = 'imza_sirkuleri';
    public const TYPE_OTHER                 = 'diger';

    protected $fillable = [
        'tenant_application_id',
        'type',
        'path',
        'original_filename',
        'mime_type',
        'size',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(TenantApplication::class, 'tenant_application_id');
    }
}
