<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant aktif hale geldiğinde veya aktifken bilgileri güncellendiğinde fırlatılır
 * (yeni açılış, owner bilgisi/şifre değişikliği, toggleActive/activate). Bagisto
 * tarafında karşılık gelen müşteri hesabını oluşturur/günceller (bkz.
 * Modules\Bagisto\Listeners\PushTenantSync).
 */
class TenantActivated
{
    use Dispatchable;

    /**
     * @param  string|null  $plainPassword  Sadece owner şifresi bu çağrıda
     *                                      belirlendiyse/değiştiyse dolu gelir;
     *                                      aksi halde Bagisto tarafındaki mevcut
     *                                      şifre korunur (webhook payload'ında
     *                                      gönderilmez).
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?string $plainPassword = null,
    ) {}
}
