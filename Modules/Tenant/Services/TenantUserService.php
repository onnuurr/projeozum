<?php
// Modules/Tenant/Services/TenantUserService.php
namespace Modules\Tenant\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;

class TenantUserService
{
    /**
     * Tenant oluşturulurken açılan ilk (sahip/yönetici) hesap — 'tenant' rolü alır.
     */
    public function createOwner(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'], // 'hashed' cast otomatik hash'ler
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole('tenant');

            return $user->fresh();
        });
    }

    /**
     * Tenant düzenleme formundan owner hesabının ad/e-posta/şifresini günceller.
     * Boş/null gelen alanlar dokunulmadan bırakılır.
     */
    public function updateOwner(User $user, array $data): User
    {
        $payload = array_filter([
            'name'     => $data['name']     ?: null,
            'email'    => $data['email']    ?: null,
            'password' => $data['password'] ?: null, // 'hashed' cast otomatik hash'ler
        ]);

        if ($payload) {
            $user->update($payload);
        }

        return $user->fresh();
    }
}
