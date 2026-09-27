<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant owner'ının (Bagisto hesabının sahibi) e-postası değiştiğinde fırlatılır.
 * Bagisto hesapları e-postayla eşleştiği için bu olmadan bir sonraki senkron yeni
 * e-postayla ikinci bir hesap açıyor, eskisi (siparişleri/bakiyesiyle) sahipsiz
 * kalıyordu. Bagisto mevcut hesabın e-postasını değiştirir
 * (bkz. Modules\Bagisto\Listeners\PushTenantSync).
 */
class TenantOwnerEmailChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $oldEmail,
        public readonly string $newEmail,
    ) {}
}
