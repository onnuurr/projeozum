<?php

namespace Modules\Tenant\Observers;

use App\Models\User;
use Modules\Tenant\Events\TenantOwnerDetached;
use Modules\Tenant\Events\TenantOwnerEmailChanged;
use Modules\Tenant\Models\Tenant;

/**
 * Kullanıcı silme/taşıma/e-posta değişikliği Superadmin UserController'dan da (Tenant
 * servislerini atlayarak) yapılabildiği için owner'ın Bagisto hesabını etkileyen
 * değişiklikler model seviyesinde yakalanır.
 */
class TenantOwnerObserver
{
    public function deleted(User $user): void
    {
        if ($tenant = $this->ownedTenant($user, $user->tenant_id)) {
            TenantOwnerDetached::dispatch($tenant, $user->email);
        }
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('tenant_id')) {
            // Taşınma e-posta değişikliğini de kapsar: eski tenant'ın hesabı eski e-postayla kaldırılır.
            if ($tenant = $this->ownedTenant($user, $user->getOriginal('tenant_id'))) {
                TenantOwnerDetached::dispatch($tenant, $user->getOriginal('email'));
            }

            return;
        }

        if ($user->wasChanged('email') && $tenant = $this->ownedTenant($user, $user->tenant_id)) {
            TenantOwnerEmailChanged::dispatch($tenant, $user->getOriginal('email'), $user->email);
        }
    }

    /**
     * primaryUser() = tenant'ın en eski (silinmemiş) kullanıcısı; kendinden eski biri
     * hâlâ duruyorsa bu kullanıcı owner değildir, Bagisto hesabı yoktur.
     */
    private function ownedTenant(User $user, ?int $tenantId): ?Tenant
    {
        if (! $tenantId || ! $tenant = Tenant::find($tenantId)) {
            return null;
        }

        $olderUserExists = User::where('tenant_id', $tenantId)
            ->where('id', '<', $user->id)
            ->exists();

        return $olderUserExists ? null : $tenant;
    }
}
