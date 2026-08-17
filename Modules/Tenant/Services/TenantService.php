<?php

namespace Modules\Tenant\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tenant\Events\TenantActivated;
use Modules\Tenant\Events\TenantDeactivated;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Models\TenantPriceList;

class TenantService
{
    public function __construct(
        private TenantCreditService $credit,
        private TenantUserService $users,
    ) {}

    /**
     * owner_name/owner_email/owner_password verilmişse tenant ile birlikte portal'a
     * giriş yapabilecek ilk kullanıcı (owner, 'tenant' rolü) da açılır — aksi halde
     * tenant'ın kimse giremeyen bir kaydı olarak kalır.
     */
    public function create(array $data): Tenant
    {
        $ownerName     = $data['owner_name']     ?? null;
        $ownerEmail    = $data['owner_email']    ?? null;
        $ownerPassword = $data['owner_password'] ?? null;
        unset($data['owner_name'], $data['owner_email'], $data['owner_password']);

        $data['slug']        = $data['slug']        ?? Tenant::generateUniqueSlug($data['name']);
        $data['created_by']  = $data['created_by']  ?? auth()->id();
        // Phase 4 XML feed token; rotate edilebilir.
        $data['feed_secret'] = $data['feed_secret'] ?? Str::random(64);

        $tenant = DB::transaction(function () use ($data, $ownerName, $ownerEmail, $ownerPassword) {
            $tenant = Tenant::create($data);

            if ($ownerEmail) {
                $this->users->createOwner($tenant, [
                    'name'     => $ownerName ?: $tenant->name,
                    'email'    => $ownerEmail,
                    'password' => $ownerPassword,
                ]);
            }

            return $tenant;
        });

        // create()'in döndürdüğü nesne, DB varsayılanı olan is_active'i henüz
        // yansıtmaz (fillable'da yok) — kontrol için fresh() şart.
        $tenant = $tenant->fresh();

        // Owner hesabı yoksa Bagisto'da açılacak bir müşteri de yok — atla.
        // primaryUser() kasıtlı: role('tenant') filtresine bağımlı değildir.
        if ($tenant->is_active && $tenant->primaryUser) {
            TenantActivated::dispatch($tenant, $ownerPassword);
        }

        return $tenant;
    }

    /**
     * owner_name/owner_email/owner_password verilmişse mevcut owner kullanıcısı
     * (rol: 'tenant') güncellenir — yeni owner açılmaz, düzenleme formunda kullanılır.
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $wasActive     = (bool) $tenant->is_active;
        $ownerName     = $data['owner_name']     ?? null;
        $ownerEmail    = $data['owner_email']    ?? null;
        $ownerPassword = $data['owner_password'] ?? null;
        unset($data['owner_name'], $data['owner_email'], $data['owner_password'], $data['owner_password_confirmation']);

        $tenant = DB::transaction(function () use ($tenant, $data, $ownerName, $ownerEmail, $ownerPassword) {
            $tenant->update($data);

            if (($ownerName || $ownerEmail || $ownerPassword) && ($owner = $tenant->owner)) {
                $this->users->updateOwner($owner, [
                    'name'     => $ownerName,
                    'email'    => $ownerEmail,
                    'password' => $ownerPassword,
                ]);
            }

            return $tenant->fresh();
        });

        // is_active bu update'te gerçekten değişmediyse Bagisto'ya tekrar
        // "activated" push'u tetikleme (owner şifresi her seferinde null
        // gönderilip gereksiz yeniden-aktivasyon davranışına yol açıyordu).
        if ((bool) $tenant->is_active !== $wasActive) {
            $this->dispatchStatusEvent($tenant, $ownerPassword);
        }

        return $tenant;
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->update([
            'is_active' => false,
        ]);

        TenantDeactivated::dispatch($tenant->fresh());
    }

    public function activate(Tenant $tenant): void
    {
        $tenant->update([
            'is_active'    => true,
            'activated_at' => now(),
        ]);

        $this->dispatchStatusEvent($tenant->fresh());
    }

    /**
     * `update()`/`activate()` sonrası, tenant'ın GÜNCEL is_active durumuna göre
     * Bagisto senkron event'ini seçer. Owner hesabı yoksa (henüz kimse için
     * Bagisto tarafında bir hesap açılmadıysa) sessizce atlanır.
     */
    private function dispatchStatusEvent(Tenant $tenant, ?string $plainPassword = null): void
    {
        // primaryUser() kasıtlı: role('tenant') filtresine (owner()) bağımlı
        // değildir — permission altyapısı kurulmamış olsa bile senkron çalışır.
        if (! $tenant->primaryUser) {
            return;
        }

        if ($tenant->is_active) {
            TenantActivated::dispatch($tenant, $plainPassword);
        } else {
            TenantDeactivated::dispatch($tenant);
        }
    }

    public function getActiveTenants(): Collection
    {
        return Tenant::where('is_active', true)->with('type')->get();
    }

    public function getPriceListForTenant(Tenant $tenant): Collection
    {
        return $tenant->priceLists()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->get();
    }

    public function addPriceList(Tenant $tenant, array $data): TenantPriceList
    {
        return $tenant->priceLists()->create($data);
    }

    public function createInvoice(Tenant $tenant, array $data): TenantInvoice
    {
        return $tenant->invoices()->create($data);
    }

    public function markInvoicePaid(TenantInvoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Fatura ödendi → tenant'ın borcu o kadar düşer.
        $this->credit->credit(
            tenant: $invoice->tenant,
            amount: (float) $invoice->amount,
            reason: 'invoice_paid',
            invoiceId: $invoice->id,
        );
    }
}
