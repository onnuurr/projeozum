<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayi portalı başvurusunda başvuru sahibini ilgilendiren bir adım gerçekleştiğinde fırlatılır.
 * Bagisto modülü dinler ve mağazaya iletir; başvuru sahibine e-postayı mağaza gönderir
 * (bkz. Modules\Bagisto\Listeners\PushTenantApplicationStage).
 */
class TenantApplicationStageChanged
{
    use Dispatchable;

    public const DOCUMENTS_REQUESTED = 'documents_requested';

    public const MISSING_DOCUMENTS = 'missing_documents';

    public const ACCOUNT_OPENED = 'account_opened';

    public const REJECTED = 'rejected';

    /**
     * @param  self::*  $stage
     */
    public function __construct(
        public readonly TenantApplication $application,
        public readonly string $stage,
    ) {}
}
