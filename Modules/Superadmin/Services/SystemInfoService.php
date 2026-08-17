<?php

namespace Modules\Superadmin\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemInfoService
{
    private const CACHE_KEY        = 'superadmin:system-info';
    private const CACHE_KEY_STATIC = 'superadmin:system-info:static';
    private const CACHE_KEY_REVERB = 'superadmin:system-info:reverb';
    private const CACHE_TTL        = 8;    // canlı (CPU/RAM/uptime) — saniye (frontend 5sn'de bir poll eder)
    private const CACHE_TTL_STATIC = 300;  // statik (versiyon/sayım/disk) — saniye
    private const CACHE_TTL_REVERB = 10;   // reverb TCP probe — saniye

    /**
     * Tam payload — CPU/RAM/uptime için Windows'ta 3 PowerShell process
     * spawn eder (cold start nedeniyle 4-6 sn). Sadece polling endpoint'i
     * (/superadmin/system-info) için kullanılmalı; initial page render'ı
     * bloklamasın.
     */
    public function payload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->buildPayload(live: true));
    }

    /**
     * Initial page render için hızlı payload — PowerShell çağrıları atlanır,
     * canlı metrikler placeholder ile döner. Frontend polling birkaç saniye
     * sonra gerçek değerleri ekrana basar.
     */
    public function staticPayload(): array
    {
        return Cache::remember(self::CACHE_KEY_STATIC, self::CACHE_TTL_STATIC, fn () => $this->buildPayload(live: false));
    }

    private function buildPayload(bool $live): array
    {
        [$memUsedMB, $memTotalMB]   = $live ? $this->memory() : [0, 0];
        [$diskUsedGB, $diskTotalGB] = $this->disk();

        return [
            'cpuUsagePct'      => $live ? $this->cpuUsage() : 0,
            'memoryUsageMB'    => $memUsedMB,
            'memoryTotalMB'    => $memTotalMB,
            'diskUsageGB'      => $diskUsedGB,
            'diskTotalGB'      => $diskTotalGB,
            'uptime'           => $live ? $this->uptime() : '—',
            'phpVersion'       => PHP_VERSION,
            'laravelVersion'   => app()->version(),
            'inertiaVersion'   => $this->composerPackageVersion('inertiajs/inertia-laravel') ?? '—',
            'vueVersion'       => $this->npmPackageVersion('vue') ?? '—',
            'mysqlVersion'     => $this->mysqlVersion(),
            'redisVersion'     => $this->redisVersion(),
            'webServer'        => $_SERVER['SERVER_SOFTWARE'] ?? '—',
            'serverOs'         => $this->osLabel(),
            'totalTenants'     => $this->safeCount('tenants'),
            'totalUsers'       => $this->safeCount('users', User::class),
            'totalOrders'      => $this->safeCount('orders'),
            'queueJobsPending' => $this->safeCount('jobs'),
            'queueJobsFailed'  => $this->safeCount('failed_jobs'),
            'reverb'           => $this->reverbStatus(),
            'liveMetrics'      => $live,
        ];
    }

    /**
     * `php artisan reverb:start` çalışıyor mu? Konfigürasyondaki host:port'a
     * 250 ms timeout'lu bir TCP bağlantısı dener; başarılıysa "running".
     * Cevap kısa süreli cache'lenir — sayfa render'ını uzatmamak için.
     */
    private function reverbStatus(): array
    {
        $host = (string) env('REVERB_HOST', '127.0.0.1');
        $port = (int) env('REVERB_PORT', 8080);

        return Cache::remember(
            self::CACHE_KEY_REVERB,
            self::CACHE_TTL_REVERB,
            function () use ($host, $port) {
                $running = false;
                $error   = null;

                try {
                    $sock = @fsockopen($host, $port, $errno, $errstr, 0.25);
                    if ($sock) {
                        $running = true;
                        fclose($sock);
                    } else {
                        $error = $errstr ?: 'Bağlantı reddedildi';
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }

                return [
                    'running' => $running,
                    'host'    => $host,
                    'port'    => $port,
                    'error'   => $running ? null : $error,
                ];
            }
        );
    }

    private function cpuUsage(): int
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = 'powershell -NoProfile -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average"';
                $out = @shell_exec($cmd);
                $val = trim((string) $out);
                if ($val !== '' && is_numeric($val)) {
                    return (int) round((float) $val);
                }
                return 0;
            }

            // /proc/stat'tan iki örnekleme arasındaki delta — `top` ile aynı yöntem.
            // 1 dakikalık load average'ı yüzdeye çevirmekten (I/O bekleyen prosesleri de
            // sayar, anlık CPU kullanımını yansıtmaz) daha doğru bir sonuç verir.
            $pct = $this->cpuUsageFromProcStat();
            if ($pct !== null) {
                return $pct;
            }

            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cores = (int) @shell_exec('nproc') ?: 1;
                return (int) min(100, round(($load[0] / max(1, $cores)) * 100));
            }
        } catch (Throwable) {
        }
        return 0;
    }

    private function cpuUsageFromProcStat(): ?int
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $read = static function (): ?array {
            $line = strtok((string) file_get_contents('/proc/stat'), "\n");
            if (! $line || ! str_starts_with($line, 'cpu ')) {
                return null;
            }
            $fields = preg_split('/\s+/', trim($line));
            array_shift($fields); // "cpu" etiketini at
            return array_map('intval', $fields);
        };

        $first = $read();
        if ($first === null) {
            return null;
        }
        usleep(200_000); // 200ms örnekleme aralığı
        $second = $read();
        if ($second === null) {
            return null;
        }

        // Alanlar: user, nice, system, idle, iowait, irq, softirq, steal, ...
        $idleFirst  = ($first[3] ?? 0) + ($first[4] ?? 0);
        $idleSecond = ($second[3] ?? 0) + ($second[4] ?? 0);
        $totalDelta = array_sum($second) - array_sum($first);
        $idleDelta  = $idleSecond - $idleFirst;

        if ($totalDelta <= 0) {
            return null;
        }

        return (int) max(0, min(100, round((1 - $idleDelta / $totalDelta) * 100)));
    }

    /** @return array{0:int,1:int} [usedMB, totalMB] */
    private function memory(): array
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = 'powershell -NoProfile -Command "$os = Get-CimInstance Win32_OperatingSystem; \"$($os.FreePhysicalMemory),$($os.TotalVisibleMemorySize)\""';
                $out = @shell_exec($cmd);
                if ($out && preg_match('/(\d+),(\d+)/', $out, $m)) {
                    $freeKB  = (int) $m[1];
                    $totalKB = (int) $m[2];
                    return [(int) round(($totalKB - $freeKB) / 1024), (int) round($totalKB / 1024)];
                }
            } elseif (is_readable('/proc/meminfo')) {
                $info = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)/', $info, $t);
                preg_match('/MemAvailable:\s+(\d+)/', $info, $a);
                if ($t && $a) {
                    $totalKB = (int) $t[1];
                    $availKB = (int) $a[1];
                    return [(int) round(($totalKB - $availKB) / 1024), (int) round($totalKB / 1024)];
                }
            }
        } catch (Throwable) {
        }
        return [0, 1];
    }

    /** @return array{0:float,1:float} [usedGB, totalGB] */
    private function disk(): array
    {
        try {
            $path  = base_path();
            $total = @disk_total_space($path) ?: 0;
            $free  = @disk_free_space($path) ?: 0;
            $gb    = 1024 ** 3;
            return [
                round(($total - $free) / $gb, 1),
                round($total / $gb, 1) ?: 1,
            ];
        } catch (Throwable) {
            return [0.0, 1.0];
        }
    }

    private function uptime(): string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $out = @shell_exec('powershell -NoProfile -Command "(Get-Date) - (Get-CimInstance Win32_OperatingSystem).LastBootUpTime | ForEach-Object { [int]$_.TotalSeconds }" 2>nul');
                $sec = (int) trim((string) $out);
                if ($sec > 0) {
                    return $this->formatUptime($sec);
                }
            } elseif (is_readable('/proc/uptime')) {
                $sec = (int) floatval(file_get_contents('/proc/uptime'));
                if ($sec > 0) {
                    return $this->formatUptime($sec);
                }
            }
        } catch (Throwable) {
        }
        return '—';
    }

    private function formatUptime(int $seconds): string
    {
        $days  = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins  = intdiv($seconds % 3600, 60);
        if ($days > 0)  return "{$days}g {$hours}sa";
        if ($hours > 0) return "{$hours}sa {$mins}dk";
        return "{$mins}dk";
    }

    private function mysqlVersion(): string
    {
        try {
            $driver = DB::connection()->getDriverName();
            $row = DB::selectOne('SELECT VERSION() as v');
            $raw = (string) ($row?->v ?? '');
            if ($raw === '') return '—';

            return match ($driver) {
                'pgsql' => preg_match('/PostgreSQL\s+([\d.]+)/i', $raw, $m) ? "PostgreSQL {$m[1]}" : $raw,
                'mysql' => preg_match('/^([\d.]+)/', $raw, $m) ? "MySQL {$m[1]}" : $raw,
                default => $raw,
            };
        } catch (Throwable) {
            return '—';
        }
    }

    private function redisVersion(): string
    {
        try {
            if (! class_exists(\Illuminate\Support\Facades\Redis::class)) {
                return '—';
            }
            $info = \Illuminate\Support\Facades\Redis::connection()->client()->info('server');
            return $info['redis_version'] ?? '—';
        } catch (Throwable) {
            return '—';
        }
    }

    private function osLabel(): string
    {
        $family = PHP_OS_FAMILY;
        $release = php_uname('r');
        return trim($family . ' ' . $release);
    }

    private function safeCount(string $table, ?string $model = null): int
    {
        try {
            if (! Schema::hasTable($table)) return 0;
            return $model ? $model::query()->count() : DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function composerPackageVersion(string $package): ?string
    {
        $lock = base_path('composer.lock');
        if (! is_readable($lock)) return null;
        $data = json_decode((string) file_get_contents($lock), true);
        foreach (($data['packages'] ?? []) as $p) {
            if (($p['name'] ?? null) === $package) {
                return ltrim((string) ($p['version'] ?? ''), 'v') ?: null;
            }
        }
        return null;
    }

    private function npmPackageVersion(string $package): ?string
    {
        $lock = base_path('package-lock.json');
        if (is_readable($lock)) {
            $data = json_decode((string) file_get_contents($lock), true);
            $v = $data['packages']["node_modules/{$package}"]['version'] ?? null;
            if ($v) return $v;
        }
        $json = base_path('package.json');
        if (is_readable($json)) {
            $data = json_decode((string) file_get_contents($json), true);
            foreach (['dependencies', 'devDependencies'] as $key) {
                if (isset($data[$key][$package])) {
                    return ltrim((string) $data[$key][$package], '^~');
                }
            }
        }
        return null;
    }
}
