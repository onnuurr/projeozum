<?php

namespace Tests\Unit\Superadmin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Modules\Superadmin\Services\VersionCheckService;
use Tests\TestCase;

class VersionCheckServiceTest extends TestCase
{
    private VersionCheckService $service;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('superadmin.version_policy', [
            'eol_warning_days' => 90,
            'components' => [
                'php' => [
                    'minimum' => '8.2',
                    'recommended' => '8.3',
                    'eol' => ['8.2' => '2026-12-31', '8.3' => '2027-12-31'],
                ],
                'database' => [
                    'minimum' => '14',
                    'eol' => ['1' => '2000-01-01', '14' => '2026-11-12', '16' => '2028-11-09'],
                ],
                'webServer' => ['minimum' => '1.24'],
                'redisFeed' => ['source' => ['endoflife' => 'redis']],
                'inertia' => ['source' => ['packagist' => 'inertiajs/inertia-laravel']],
            ],
        ]);

        Cache::forget(VersionCheckService::CACHE_KEY_RELEASES);
        $this->service = new VersionCheckService;
        $this->today = CarbonImmutable::parse('2026-09-26');
    }

    public function test_normalizes_raw_version_strings(): void
    {
        $this->assertSame('16.14', $this->service->normalize('PostgreSQL 16.14'));
        $this->assertSame('1.30.4', $this->service->normalize('nginx/1.30.4'));
        $this->assertSame('2.0.24', $this->service->normalize('v2.0.24'));
        $this->assertNull($this->service->normalize('—'));
        $this->assertNull($this->service->normalize(null));
    }

    public function test_supported_version_is_ok(): void
    {
        $result = $this->service->check(['php' => '8.3.12'], $this->today);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('2027-12-31', $result['components']['php']['eol']);
    }

    public function test_below_minimum_is_critical(): void
    {
        $result = $this->service->check(['php' => '8.1.30'], $this->today);

        $this->assertSame('critical', $result['components']['php']['status']);
    }

    public function test_below_recommended_is_warning(): void
    {
        // 8.2 EOL'ü 96 gün uzakta (uyarı eşiği dışında) → yalnızca "önerilen" kuralı tetiklenir
        $result = $this->service->check(['php' => '8.2.20'], $this->today);

        $this->assertSame('warning', $result['components']['php']['status']);
        $this->assertStringContainsString('Önerilen', $result['components']['php']['message']);
    }

    public function test_eol_approaching_is_warning_and_passed_is_critical(): void
    {
        $soon = $this->service->check(['database' => 'PostgreSQL 14.12'], $this->today);
        $this->assertSame('warning', $soon['components']['database']['status']);

        $passed = $this->service->check(['database' => 'PostgreSQL 14.12'], CarbonImmutable::parse('2026-11-20'));
        $this->assertSame('critical', $passed['components']['database']['status']);
    }

    public function test_eol_branch_matching_does_not_confuse_1_with_16(): void
    {
        $result = $this->service->check(['database' => 'PostgreSQL 16.14'], $this->today);

        $this->assertSame('ok', $result['components']['database']['status']);
        $this->assertSame('2028-11-09', $result['components']['database']['eol']);
    }

    public function test_unknown_version_or_missing_policy_is_unknown(): void
    {
        $result = $this->service->check(['redis' => '7.0.15', 'webServer' => '—'], $this->today);

        $this->assertSame('unknown', $result['components']['redis']['status']);
        $this->assertSame('unknown', $result['components']['webServer']['status']);
        $this->assertSame('unknown', $result['status']);
    }

    public function test_overall_status_is_the_worst_component(): void
    {
        $result = $this->service->check([
            'php' => '8.3.12',
            'webServer' => 'nginx/1.22.1',
            'redis' => '7.0.15',
        ], $this->today);

        $this->assertSame('critical', $result['status']);
    }

    public function test_refresh_fetches_release_data_and_flags_patch_and_eol(): void
    {
        Http::fake([
            'endoflife.date/api/redis.json' => Http::response([
                ['cycle' => '7.2', 'latest' => '7.2.16', 'eol' => '2029-12-01'],
                ['cycle' => '7.0', 'latest' => '7.0.15', 'eol' => '2024-07-29'],
            ]),
            'repo.packagist.org/*' => Http::response(['packages' => ['inertiajs/inertia-laravel' => [
                ['version' => 'v3.4.0'], ['version' => 'v3.5.0-beta1'], ['version' => 'v2.0.30'], ['version' => 'v2.0.24'],
            ]]]),
        ]);

        $refresh = $this->service->refreshReleases();
        $this->assertSame(['redisFeed', 'inertia'], $refresh['updated']);

        $result = $this->service->check([
            'redisFeed' => '7.0.15',
            'inertia' => '2.0.24',
        ], $this->today);

        $redis = $result['components']['redisFeed'];
        $this->assertSame('critical', $redis['status']);
        $this->assertSame('7.2.16', $redis['latestOverall']);

        $inertia = $result['components']['inertia'];
        $this->assertSame('warning', $inertia['status']);
        $this->assertTrue($inertia['updateAvailable']);
        $this->assertSame('2.0.30', $inertia['latest']);
        $this->assertTrue($inertia['upgradeAvailable']);
        $this->assertSame('3.4.0', $inertia['latestOverall']);
        $this->assertNotNull($result['releasesCheckedAt']);
    }

    public function test_latest_patch_on_supported_branch_is_ok_even_if_newer_branch_exists(): void
    {
        Http::fake(['*' => Http::response(['packages' => ['inertiajs/inertia-laravel' => [
            ['version' => 'v3.4.0'], ['version' => 'v2.0.30'],
        ]]])]);
        config()->set('superadmin.version_policy.components', [
            'inertia' => ['source' => ['packagist' => 'inertiajs/inertia-laravel']],
        ]);
        $this->service->refreshReleases();

        $inertia = $this->service->check(['inertia' => '2.0.30'], $this->today)['components']['inertia'];

        $this->assertSame('ok', $inertia['status']);
        $this->assertFalse($inertia['updateAvailable']);
        $this->assertTrue($inertia['upgradeAvailable']);
    }

    public function test_failed_source_keeps_previous_release_data(): void
    {
        $redisDown = false;
        Http::fake(function ($request) use (&$redisDown) {
            if (str_contains($request->url(), 'redis')) {
                return $redisDown
                    ? Http::response('down', 503)
                    : Http::response([['cycle' => '7.2', 'latest' => '7.2.16', 'eol' => '2029-12-01']]);
            }

            return Http::response([['cycle' => '3.5', 'latest' => '3.5.43', 'eol' => null]]);
        });
        config()->set('superadmin.version_policy.components', [
            'redisFeed' => ['source' => ['endoflife' => 'redis']],
            'vue' => ['source' => ['endoflife' => 'vue']],
        ]);
        $this->service->refreshReleases();

        $redisDown = true;
        $refresh = $this->service->refreshReleases();

        $this->assertSame(['vue'], $refresh['updated']);
        $this->assertArrayHasKey('redisFeed', $refresh['failed']);
        $this->assertSame('7.2.16', $this->service->cachedReleases()['components']['redisFeed'][0]['latest']);
    }

    private const APT_POLICY = <<<'TXT'
redis-server:
  Installed: 5:7.0.15-1ubuntu0.24.04.4
  Candidate: 5:7.0.15-1ubuntu0.24.04.5
  Version table:
     5:7.0.15-1ubuntu0.24.04.5 500
        500 http://security.ubuntu.com/ubuntu noble-security/universe amd64 Packages
 *** 5:7.0.15-1ubuntu0.24.04.4 500
        500 http://archive.ubuntu.com/ubuntu noble-updates/main amd64 Packages
        100 /var/lib/dpkg/status
postgresql-16:
  Installed: 16.14-0ubuntu0.24.04.1
  Candidate: 16.14-0ubuntu0.24.04.1
  Version table:
 *** 16.14-0ubuntu0.24.04.1 500
        500 http://archive.ubuntu.com/ubuntu noble-updates/main amd64 Packages
        100 /var/lib/dpkg/status
nginx:
  Installed: 1.30.4-1+clp-noble
  Candidate: 1.30.4-1+clp-noble
  Version table:
 *** 1.30.4-1+clp-noble 1000
       1000 https://repo.example.com noble/nginx amd64 Packages
        100 /var/lib/dpkg/status
     1.24.0-2ubuntu7.15 500
        500 http://archive.ubuntu.com/ubuntu noble-updates/main amd64 Packages
TXT;

    public function test_parses_apt_policy_with_candidate_origin(): void
    {
        $parsed = $this->service->parseAptPolicy(self::APT_POLICY);

        // Aday sürümün deposu esas alınır (kurulu sürümün satırı değil)
        $this->assertSame('ubuntu-universe', $parsed['redis-server']['origin']);
        $this->assertSame('5:7.0.15-1ubuntu0.24.04.5', $parsed['redis-server']['candidate']);
        $this->assertSame('ubuntu-main', $parsed['postgresql-16']['origin']);
        $this->assertSame('third-party', $parsed['nginx']['origin']);
        $this->assertSame('repo.example.com', $parsed['nginx']['source']);
    }

    public function test_apt_managed_components_are_judged_by_the_package_repository(): void
    {
        $lists = sys_get_temp_dir().'/vc-apt-lists-'.uniqid();
        mkdir($lists);
        touch($lists.'/noble-updates_InRelease', $this->today->subDays(2)->getTimestamp());

        config()->set('superadmin.version_policy.apt', [
            'distro_eol' => '2029-05-31', 'lists_stale_days' => 14, 'lists_path' => $lists,
        ]);
        config()->set('superadmin.version_policy.components', [
            'redis' => ['source' => ['endoflife' => 'redis'], 'apt' => 'redis-server', 'recommended' => '7.2'],
            'database' => ['source' => ['endoflife' => 'postgresql'], 'apt' => 'postgresql-{major}'],
            'webServer' => ['source' => ['endoflife' => 'nginx'], 'apt' => 'nginx'],
        ]);

        Http::fake([
            'endoflife.date/api/redis.json' => Http::response([
                ['cycle' => '8.10', 'latest' => '8.10.2', 'eol' => false],
                ['cycle' => '7.0', 'latest' => '7.0.15', 'eol' => '2024-07-29'],
            ]),
            'endoflife.date/api/postgresql.json' => Http::response([
                ['cycle' => '16', 'latest' => '16.15', 'eol' => '2028-11-09'],
            ]),
            'endoflife.date/api/nginx.json' => Http::response([
                ['cycle' => '1.30', 'latest' => '1.30.5', 'eol' => false],
            ]),
        ]);
        Process::fake(function ($process) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;

            return match (true) {
                str_starts_with($cmd, 'dpkg-query') => Process::result("redis-server\tii \npostgresql-16\tii \nnginx\tii \npostgresql-9.1\tun \n"),
                str_starts_with($cmd, 'apt-cache') => Process::result(self::APT_POLICY),
                str_starts_with($cmd, 'dpkg --compare-versions') => Process::result(exitCode: 0),
                default => Process::result(exitCode: 1),
            };
        });

        $this->service->refreshReleases();
        $c = $this->service->check([
            'redis' => '7.0.15',
            'database' => 'PostgreSQL 16.14',
            'webServer' => 'nginx/1.30.4',
        ], $this->today)['components'];

        // Universe + resmi EOL geçti → kritik değil uyarı; aynı upstream'in yeni paket revizyonu gösterilir
        $this->assertSame('warning', $c['redis']['status']);
        $this->assertSame('apt', $c['redis']['managedBy']);
        $this->assertTrue($c['redis']['updateAvailable']);
        $this->assertSame('7.0.15-1ubuntu0.24.04.5', $c['redis']['latest']);
        $this->assertStringContainsString('garantisi yok', $c['redis']['message']);
        $this->assertStringNotContainsString('Önerilen', $c['redis']['message']);

        // Resmi 16.15 çıktı ama apt sunmuyor → güncelleme yok; destek süresi dağıtımınki
        $this->assertSame('ok', $c['database']['status']);
        $this->assertFalse($c['database']['updateAvailable']);
        $this->assertSame('2029-05-31', $c['database']['eol']);
        $this->assertSame('Ubuntu main', $c['database']['packageSource']);

        // Üçüncü taraf depo resmi yamayı henüz yayınlamadı → bilgi notu, durum ok
        $this->assertSame('ok', $c['webServer']['status']);
        $this->assertStringContainsString('1.30.5', $c['webServer']['message']);

        array_map('unlink', glob($lists.'/*'));
        rmdir($lists);
    }

    public function test_stale_apt_lists_are_flagged(): void
    {
        $lists = sys_get_temp_dir().'/vc-apt-lists-'.uniqid();
        mkdir($lists);
        touch($lists.'/noble-updates_InRelease', $this->today->subDays(40)->getTimestamp());

        config()->set('superadmin.version_policy.apt', ['lists_stale_days' => 14, 'lists_path' => $lists, 'distro_eol' => '2029-05-31']);
        config()->set('superadmin.version_policy.components', [
            'database' => ['apt' => 'postgresql-{major}'],
        ]);
        Process::fake(fn ($process) => str_starts_with(implode(' ', (array) $process->command), 'apt-cache')
            ? Process::result(self::APT_POLICY)
            : Process::result("postgresql-16\tii \n"));

        $this->service->refreshReleases();
        $db = $this->service->check(['database' => 'PostgreSQL 16.14'], $this->today)['components']['database'];

        $this->assertSame('warning', $db['status']);
        $this->assertStringContainsString('40 gündür yenilenmemiş', $db['message']);

        array_map('unlink', glob($lists.'/*'));
        rmdir($lists);
    }

    public function test_running_version_not_matching_installed_package_falls_back_to_upstream(): void
    {
        config()->set('superadmin.version_policy.components', [
            'redis' => ['source' => ['endoflife' => 'redis'], 'apt' => 'redis-server'],
        ]);
        Http::fake(['*' => Http::response([['cycle' => '7.4', 'latest' => '7.4.11', 'eol' => '2029-12-01']])]);
        Process::fake(fn ($process) => str_starts_with(implode(' ', (array) $process->command), 'apt-cache')
            ? Process::result(self::APT_POLICY)
            : Process::result("redis-server\tii \n"));

        $this->service->refreshReleases();
        // Elle derlenmiş 7.4.2 çalışıyor; apt'teki 7.0.15 paketi ona ait değil
        $redis = $this->service->check(['redis' => '7.4.2'], $this->today)['components']['redis'];

        $this->assertNull($redis['managedBy']);
        $this->assertSame('7.4.11', $redis['latest']);
    }

    public function test_update_instructions_per_package_manager(): void
    {
        $base = ['managedBy' => null, 'updateAvailable' => false, 'aptListsStale' => false, 'branchChangeNeeded' => false, 'package' => null];

        $apt = $this->service->updateInstructions(['managedBy' => 'apt', 'updateAvailable' => true, 'package' => 'postgresql-16'] + $base, []);
        $this->assertSame('sudo apt update && sudo apt install --only-upgrade postgresql-16', $apt['updateCommand']);

        $stale = $this->service->updateInstructions(['managedBy' => 'apt', 'aptListsStale' => true, 'branchChangeNeeded' => true, 'package' => 'redis-server'] + $base, []);
        $this->assertSame('sudo apt update && apt list --upgradable', $stale['updateCommand']);
        $this->assertStringContainsString('geçiş planı', $stale['updateNote']);

        $composer = $this->service->updateInstructions(['updateAvailable' => true] + $base, ['composer' => 'laravel/framework']);
        $this->assertSame('composer update laravel/framework --with-dependencies', $composer['updateCommand']);

        $npm = $this->service->updateInstructions(['updateAvailable' => true] + $base, ['npm' => 'vue']);
        $this->assertSame('npm update vue', $npm['updateCommand']);
        $this->assertStringContainsString('build', $npm['updateNote']);

        $none = $this->service->updateInstructions($base, ['composer' => 'laravel/framework']);
        $this->assertNull($none['updateCommand']);
        $this->assertNull($none['updateNote']);
    }
}
