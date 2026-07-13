<?php

namespace Modules\Tenant\Jobs\Marketplace;

use Closure;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService;
use Throwable;

/**
 * Marketplace job'larında tekrarlanan sync-log lifecycle iskeleti (start → work → finish,
 * hata halinde fail) — yeni bir job eklerken bu adımlardan birinin (özellikle failSyncLog)
 * unutulup sync_log kaydının "running" durumunda asılı kalmasını önler.
 */
trait RunsMarketplaceSyncLog
{
    /**
     * @param Closure(): array $work İşi yapar, finishSyncLog'a geçirilecek attributes'ı döner.
     */
    private function runSync(TenantMarketplaceSyncService $sync, Tenant $tenant, string $marketplace, string $operation, Closure $work): void
    {
        $log = $sync->startSyncLog($tenant, $marketplace, $operation);

        try {
            $sync->finishSyncLog($log, $work());
        } catch (Throwable $e) {
            $sync->failSyncLog($log, $e->getMessage());
            throw $e;
        }
    }
}
