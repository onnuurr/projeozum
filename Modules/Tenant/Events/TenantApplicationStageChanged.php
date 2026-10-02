<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayi portalı başvurusunda başvuru sahibini ilgilendiren bir adım gerçekleştiğinde fırlatılır.
 * Bagisto modülü dinler ve başvuru sahibine adımın e-postasını SaaS'tan gönderir
 * (bkz. Modules\Bagisto\Listeners\SendTenantApplicationStageMail).
 */
class TenantApplicationStageChanged
{
    use Dispatchable;

    public const RECEIVED = 'received';

    public const DOCUMENTS_REQUESTED = 'documents_requested';

    /** Resmi belge adımı açık ama bayi henüz göndermedi; admin hatırlatması. */
    public const DOCUMENTS_REMINDER = 'documents_reminder';

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
