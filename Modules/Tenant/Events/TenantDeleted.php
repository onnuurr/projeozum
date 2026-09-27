<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant silindiğinde (soft delete) fırlatılır. Bagisto tarafındaki karşılık gelen
 * müşteri hesabını kaldırır (bkz. Modules\Bagisto\Listeners\PushTenantSync).
 *
 * Silme, bağlı kullanıcılar kaldırılmadan yapılamadığı için olay anında `primaryUser`
 * artık yoktur — Bagisto hesabının e-postası silinmeden ÖNCE (soft-deleted owner
 * dahil) yakalanıp burada taşınır. Owner başka bir tenant'a taşındıysa null'dır:
 * o e-postanın Bagisto hesabı artık diğer tenant'a aittir, dokunulmamalı.
 */
class TenantDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?string $ownerEmail,
    ) {}
}
