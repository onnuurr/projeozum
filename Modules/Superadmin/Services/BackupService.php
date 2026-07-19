<?php

namespace Modules\Superadmin\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Superadmin\Models\BackupRun;
use RuntimeException;
use Throwable;

/**
 * Veritabanı (pg_dump) + proje dosyaları (tar) yedeğini alıp rclone ile uzak
 * depolamaya (Google Drive) kopyalar, ardından retention süresini aşan uzak
 * yedekleri temizler. Her çalışma bir BackupRun kaydına işlenir (superadmin
 * panelinden takip için) — kayıt başarısız olsa bile yazılır.
 *
 * Önceki manuel /root/backup.sh betiğinin yerini alır: DB kimlik bilgileri
 * artık plaintext değil, config('database') üzerinden okunur; komutlar shell
 * string birleştirme yerine Process array argümanlarıyla çalıştığından shell
 * injection riski yoktur.
 */
class BackupService
{
    public function run(string $triggeredBy = 'schedule'): BackupRun
    {
        $backupRun = BackupRun::create([
            'status'       => BackupRun::STATUS_RUNNING,
            'triggered_by' => $triggeredBy,
            'started_at'   => now(),
        ]);

        $tempDir = config('superadmin.backup.temp_dir');
        $date    = now()->format('Y-m-d_H-i');

        // Adım adım ilerlerken doldurulur — başarısızlık hangi adımda olursa olsun
        // o ana kadar tamamlanan boyutlar kayda geçsin diye dışarıda tutulur.
        $dumpBytes  = null;
        $filesBytes = null;

        try {
            File::ensureDirectoryExists($tempDir);

            $dumpPath  = "{$tempDir}/db_{$date}.dump";
            $filesPath = "{$tempDir}/files_{$date}.tar.gz";

            $dumpBytes  = $this->dumpDatabase($dumpPath);
            $filesBytes = $this->archiveFiles($filesPath);

            $remote     = config('superadmin.backup.rclone_remote');
            $remotePath = "{$remote}/{$date}";
            $this->uploadToRemote($tempDir, $remotePath);
            $this->pruneRemote($remote);

            $backupRun->update([
                'status'           => BackupRun::STATUS_SUCCESS,
                'finished_at'      => now(),
                'duration_seconds' => (int) round(abs(now()->diffInSeconds($backupRun->started_at))),
                'db_dump_bytes'    => $dumpBytes,
                'files_bytes'      => $filesBytes,
                'remote_path'      => $remotePath,
            ]);

            return $backupRun->fresh();
        } catch (Throwable $e) {
            $backupRun->update([
                'status'           => BackupRun::STATUS_FAILED,
                'finished_at'      => now(),
                'duration_seconds' => (int) round(abs(now()->diffInSeconds($backupRun->started_at))),
                'db_dump_bytes'    => $dumpBytes,
                'files_bytes'      => $filesBytes,
                'error_message'    => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    private function dumpDatabase(string $dumpPath): int
    {
        $connectionName = config('database.default');
        $connection     = config("database.connections.{$connectionName}");

        $result = Process::env(['PGPASSWORD' => $connection['password'] ?? ''])
            ->timeout(600)
            ->run([
                'pg_dump',
                '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                '-p', (string) ($connection['port'] ?? 5432),
                '-U', (string) ($connection['username'] ?? ''),
                '-F', 'c', '-b',
                '-f', $dumpPath,
                (string) ($connection['database'] ?? ''),
            ]);

        if (! $result->successful()) {
            throw new RuntimeException("pg_dump başarısız: {$result->errorOutput()}");
        }

        return File::exists($dumpPath) ? File::size($dumpPath) : 0;
    }

    private function archiveFiles(string $filesPath): int
    {
        $sourceDir    = base_path();
        $excludeArgs  = collect(config('superadmin.backup.exclude', []))
            ->map(fn (string $pattern) => "--exclude={$pattern}")
            ->all();

        $result = Process::timeout(1800)->run([
            'tar', '-czf', $filesPath,
            ...$excludeArgs,
            '-C', $sourceDir, '.',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Dosya arşivleme (tar) başarısız: {$result->errorOutput()}");
        }

        return File::exists($filesPath) ? File::size($filesPath) : 0;
    }

    private function uploadToRemote(string $tempDir, string $remotePath): void
    {
        $result = Process::timeout(1800)->run([
            'rclone', 'copy', $tempDir, $remotePath, '--drive-chunk-size', '64M',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("rclone yükleme başarısız: {$result->errorOutput()}");
        }
    }

    /** Retention süresini aşan uzak yedek klasörlerini temizler (best-effort — asıl yedek zaten yüklendi). */
    private function pruneRemote(string $remote): void
    {
        $retentionDays = config('superadmin.backup.retention_days', 30);

        Process::timeout(300)->run(['rclone', 'delete', $remote, '--min-age', "{$retentionDays}d"]);
        Process::timeout(300)->run(['rclone', 'rmdirs', $remote, '--leave-root']);
    }
}
