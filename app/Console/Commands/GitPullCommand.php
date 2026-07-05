<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * GEÇİCİ — canlıya geçmeden önce kaldırılacak. Ayarlar > Performans
 * sekmesindeki "Git Pull" butonu bunu tetikler; sadece local ortamda çalışır.
 */
class GitPullCommand extends Command
{
    protected $signature = 'git:pull';

    protected $description = 'Geçici: local ortamda git pull çalıştırır';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Bu komut sadece local ortamda çalışır.');

            return self::FAILURE;
        }

        $result = Process::path(base_path())->timeout(120)->run('git pull');

        $this->line(trim($result->output() . $result->errorOutput()));

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
