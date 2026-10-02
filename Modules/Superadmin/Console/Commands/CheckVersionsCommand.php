<?php

namespace Modules\Superadmin\Console\Commands;

use Illuminate\Console\Command;
use Modules\Superadmin\Services\SystemInfoService;
use Modules\Superadmin\Services\VersionCheckService;

/**
 * Yazılım bileşenlerinin (PHP, Laravel, PostgreSQL, Redis, nginx, Inertia, Vue)
 * son sürüm / EOL verisini dış kaynaklardan çekip cache'e yazar. Süper admin
 * Sistem Paneli'ndeki "Yazılım sürümleri" kartı bu veriyle "güncelleme mevcut /
 * destek bitti" uyarısı gösterir (bkz. VersionCheckService).
 *
 * Zamanlaması routes/console.php'de: Schedule::command('superadmin:check-versions')->weeklyOn(1, '03:30') (her pazartesi).
 */
class CheckVersionsCommand extends Command
{
    protected $signature = 'superadmin:check-versions';

    protected $description = 'Yazılım bileşenlerinin son sürüm ve destek (EOL) bilgisini dış kaynaklardan yeniler';

    public function handle(VersionCheckService $versions, SystemInfoService $systemInfo): int
    {
        $result = $versions->refreshReleases();

        foreach ($result['updated'] as $key) {
            $this->components->twoColumnDetail($key, '<fg=green>güncellendi</>');
        }
        foreach ($result['failed'] as $key => $error) {
            $this->components->twoColumnDetail($key, "<fg=red>başarısız</> {$error}");
        }

        $systemInfo->flush();

        if ($result['updated'] === []) {
            $this->components->error('Hiçbir kaynaktan yayın verisi alınamadı.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
