<?php
// Modules/Tenant/Services/TenantUserService.php
namespace Modules\Tenant\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;

class TenantUserService
{
    /**
     * Yöneticinin alt kullanıcıya atayabileceği portal izinleri kataloğu.
     * portal.access baseline (tenant-user rolü) — burada YOK.
     * portal.users.manage ve admin izinleri kasıtlı olarak YOK.
     */
    public const ASSIGNABLE_PERMISSIONS = [
        'portal.orders.view'     => ['label' => 'Siparişleri Görüntüle', 'group' => 'Siparişler & Faturalar'],
        'portal.invoices.view'   => ['label' => 'Faturaları Görüntüle',  'group' => 'Siparişler & Faturalar'],
        'portal.credit.view'     => ['label' => 'Kredi Hareketleri',     'group' => 'Siparişler & Faturalar'],
        'portal.catalog.view'    => ['label' => 'Katalog Görüntüle',     'group' => 'Katalog & Sipariş'],
        'portal.checkout'        => ['label' => 'Sipariş Aç (Checkout)', 'group' => 'Katalog & Sipariş'],
        'marketplace.view-sales' => ['label' => 'Pazaryeri Satışları',   'group' => 'Pazaryeri'],
        'marketplace.sync'       => ['label' => 'Pazaryeri Senkron',     'group' => 'Pazaryeri'],
        'portal.financials.view' => ['label' => 'Kâr/Zarar Dashboard',   'group' => 'Finans & Araçlar'],
        'portal.calculator.use'  => ['label' => 'Kâr Hesabı',            'group' => 'Finans & Araçlar'],
        'portal.feed.access'     => ['label' => 'XML Feed Erişimi',      'group' => 'Finans & Araçlar'],
    ];

    /** @return list<string> */
    public static function assignableNames(): array
    {
        return array_keys(self::ASSIGNABLE_PERMISSIONS);
    }

    public function create(Tenant $tenant, array $data): User
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

            $user->assignRole('tenant-user');
            $user->syncPermissions($this->safePermissions($data['permissions'] ?? []));

            return $user->fresh();
        });
    }

    /**
     * Tenant oluşturulurken açılan ilk (sahip/yönetici) hesap — 'tenant' rolü alır,
     * bu rol zaten TenantPermissionSeeder'da tüm portal izinlerine sahiptir; sub-user'ın
     * aksine granüler permission ataması yapılmaz.
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
     * Tenant düzenleme formundan owner hesabının ad/e-posta/şifresini günceller —
     * sub-user update()'in aksine izin senkronu yapmaz (owner zaten rol bazlı tam yetkili).
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

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name']]);
            $user->syncPermissions($this->safePermissions($data['permissions'] ?? []));

            return $user->fresh();
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]); // 'hashed' cast
    }

    public function toggleActive(User $user): void
    {
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function delete(User $user): void
    {
        $user->delete(); // soft delete
    }

    /** Katalog dışı/izinsiz permission isteğini süz. @return list<string> */
    private function safePermissions(array $requested): array
    {
        return array_values(array_intersect($requested, self::assignableNames()));
    }
}
