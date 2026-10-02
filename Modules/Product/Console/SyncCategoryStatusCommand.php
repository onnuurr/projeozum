<?php

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Modules\Product\Services\CategoryStatusService;

/**
 * Kategori durumlarını ürünlere göre yeniden hesaplar (bkz. CategoryStatusService).
 * Normalde ürün/kategori değişikliklerinde otomatik çalışır; bu komut günlük güvenlik ağı
 * (routes/console.php) ve ilk kurulum / elle onarım içindir.
 */
class SyncCategoryStatusCommand extends Command
{
    protected $signature = 'categories:sync-status';

    protected $description = 'Kategori durumlarını yayında ürün olup olmamasına göre (alt kategoriler dahil) günceller.';

    public function handle(CategoryStatusService $service): int
    {
        $changed = $service->syncAll();
        $this->info("{$changed} kategorinin durumu güncellendi.");

        return self::SUCCESS;
    }
}
