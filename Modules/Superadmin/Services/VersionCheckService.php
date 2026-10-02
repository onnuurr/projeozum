<?php

namespace Modules\Superadmin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Sistem Paneli'ndeki "Yazılım sürümleri" kartı için kurulu sürümlerin güncel
 * olup olmadığını ve güncelleme gerekip gerekmediğini değerlendirir.
 *
 * `apt` ile kurulu sistem paketleri (PHP-FPM, PostgreSQL, Redis, nginx) resmi sürüme
 * göre değil paket deposuna göre değerlendirilir: dağıtımlar güvenlik yamalarını
 * eski sürüm numarasına ekler, ve kurulamayacak bir sürümü "güncelleme" diye
 * göstermek yanıltıcı olur (bkz. config `superadmin.version_policy` 3. madde).
 *
 * İki aşamalı çalışır:
 *  - refreshReleases(): arka planda (`superadmin:check-versions`, haftalık) dış
 *    kaynaklardan (endoflife.date / Packagist) her bileşenin sürüm dallarını,
 *    son sürümlerini ve EOL tarihlerini, `apt-cache policy`'den de kurulu/aday
 *    paket sürümlerini çekip cache'e yazar.
 *  - check(): panel isteğinde sadece cache'i okur (ağa çıkmaz — 5 sn'lik polling'i
 *    bloklamasın). Yayın verisi yoksa `superadmin.version_policy` statik tablosuna düşer.
 *
 * Durumlar (kötüden iyiye): critical > warning > unknown > ok.
 */
class VersionCheckService
{
    public const STATUS_OK       = 'ok';
    public const STATUS_WARNING  = 'warning';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_UNKNOWN  = 'unknown';

    public const CACHE_KEY_RELEASES = 'superadmin:version-releases';

    private const SEVERITY = [
        self::STATUS_OK       => 0,
        self::STATUS_UNKNOWN  => 1,
        self::STATUS_WARNING  => 2,
        self::STATUS_CRITICAL => 3,
    ];

    private const HTTP_TIMEOUT = 10;

    /**
     * @param  array<string, string|null>  $installed  bileşen => ham sürüm metni
     *                                                  (ör. 'database' => 'PostgreSQL 16.14', 'webServer' => 'nginx/1.30.4')
     * @return array{status: string, releasesCheckedAt: ?string, aptListsUpdatedAt: ?string, components: array<string, array<string, mixed>>}
     */
    public function check(array $installed, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $policy = (array) config('superadmin.version_policy.components', []);
        $warnDays = (int) config('superadmin.version_policy.eol_warning_days', 90);
        $releases = $this->cachedReleases();

        $components = [];
        foreach ($installed as $key => $raw) {
            $components[$key] = $this->checkOne(
                $raw,
                $policy[$key] ?? null,
                $releases['components'][$key] ?? null,
                $today,
                $warnDays,
                $releases['apt'] ?? null,
            );
            $components[$key] += $this->updateInstructions($components[$key], $policy[$key] ?? []);
        }

        return [
            'status'            => $this->worst(array_column($components, 'status')),
            'releasesCheckedAt' => $releases['fetchedAt'] ?? null,
            'aptListsUpdatedAt' => $releases['apt']['listsUpdatedAt'] ?? null,
            'components'        => $components,
        ];
    }

    /**
     * @param  array{minimum?: string, recommended?: string, eol?: array<string, string>}|null  $rule
     * @param  list<array{cycle: string, latest: ?string, eol: ?string}>|null  $cycles  yayın verisi (cache'ten)
     * @param  array{listsUpdatedAt: ?string, packages: array<string, array<string, mixed>>}|null  $apt  apt paket verisi (cache'ten)
     * @return array<string, mixed>
     */
    public function checkOne(?string $raw, ?array $rule, ?array $cycles, CarbonImmutable $today, int $warnDays = 90, ?array $apt = null): array
    {
        $version = $this->normalize($raw);

        if ($version === null) {
            return $this->result(null, self::STATUS_UNKNOWN, 'Sürüm tespit edilemedi.');
        }
        if ($rule === null && ! $cycles) {
            return $this->result($version, self::STATUS_UNKNOWN, 'Bu bileşen için sürüm politikası ve yayın verisi yok.');
        }

        $rule ??= [];
        $cycle = $cycles ? $this->matchCycle($version, $cycles) : null;
        $latestOverall = $cycles ? $this->latestOverall($cycles) : null;

        $package = $this->aptPackageFor($version, $rule['apt'] ?? null, $apt);
        if ($package !== null) {
            return $this->checkAptPackage($version, $rule, $package, $apt['listsUpdatedAt'] ?? null, $cycle, $latestOverall, $today, $warnDays);
        }

        $eol = $cycle['eol'] ?? $this->matchBranch($version, $rule['eol'] ?? []);
        $latest = $cycle['latest'] ?? null;
        $updateAvailable = $latest !== null && version_compare($latest, $version, '>');
        $upgradeAvailable = $latestOverall !== null && $cycle !== null
            && version_compare($latestOverall, $latest ?? $version, '>');

        $extra = compact('eol', 'latest', 'latestOverall', 'updateAvailable', 'upgradeAvailable');

        if (isset($rule['minimum']) && version_compare($version, $rule['minimum'], '<')) {
            return $this->result($version, self::STATUS_CRITICAL, "Desteklenen en düşük sürüm {$rule['minimum']}; güncelleme zorunlu.", $extra);
        }

        if ($eol !== null) {
            $eolDate = CarbonImmutable::parse($eol);
            if ($today->greaterThan($eolDate)) {
                $target = $latestOverall ? " ({$latestOverall} önerilir)" : '';

                return $this->result($version, self::STATUS_CRITICAL, "Güvenlik desteği {$eolDate->format('d.m.Y')} tarihinde bitti; yeni bir sürüm dalına geçilmeli{$target}.", $extra + ['branchChangeNeeded' => true]);
            }
            $daysLeft = (int) $today->diffInDays($eolDate);
            if ($daysLeft <= $warnDays) {
                return $this->result($version, self::STATUS_WARNING, "Güvenlik desteği {$daysLeft} gün sonra bitiyor ({$eolDate->format('d.m.Y')}).", $extra);
            }
        }

        // Aynı dalda daha yeni yama = hata/güvenlik düzeltmesi → güncelleme gerekli.
        if ($updateAvailable) {
            return $this->result($version, self::STATUS_WARNING, "Güncelleme mevcut: {$latest} (aynı sürüm dalında yama).", $extra);
        }

        if (isset($rule['recommended']) && version_compare($version, $rule['recommended'], '<')) {
            return $this->result($version, self::STATUS_WARNING, "Önerilen sürüm {$rule['recommended']} veya üstü.", $extra);
        }

        // Yeni bir dal çıkmış olması tek başına güncelleme zorunluluğu değil (mevcut dal hâlâ destekli).
        $message = $upgradeAvailable
            ? "Güncel ve destekleniyor. Yeni sürüm dalı mevcut: {$latestOverall}."
            : ($cycle !== null ? 'En son sürüm, destekleniyor.' : 'Güncel ve destekleniyor.');

        return $this->result($version, self::STATUS_OK, $message, $extra);
    }

    /**
     * Arka plan işi: her bileşenin yayın verisini dış kaynaktan çekip cache'e yazar.
     * Tek bir kaynak başarısız olursa o bileşenin önceki verisi korunur.
     *
     * @return array{updated: list<string>, failed: array<string, string>}
     */
    public function refreshReleases(): array
    {
        $policy = (array) config('superadmin.version_policy.components', []);
        $previous = $this->cachedReleases()['components'] ?? [];

        $components = [];
        $updated = [];
        $failed = [];

        foreach ($policy as $key => $rule) {
            $source = $rule['source'] ?? null;
            if (! is_array($source)) {
                continue;
            }

            try {
                $components[$key] = match (true) {
                    isset($source['endoflife']) => $this->fetchEndOfLife($source['endoflife']),
                    isset($source['packagist']) => $this->fetchPackagist($source['packagist']),
                    default => throw new \RuntimeException('Bilinmeyen kaynak türü.'),
                };
                $updated[] = $key;
            } catch (Throwable $e) {
                $failed[$key] = $e->getMessage();
                if (isset($previous[$key])) {
                    $components[$key] = $previous[$key];
                }
            }
        }

        $aptTemplates = array_values(array_filter(array_column($policy, 'apt')));
        $apt = $this->cachedReleases()['apt'] ?? null;
        if ($aptTemplates !== []) {
            try {
                $apt = $this->fetchAptPackages($aptTemplates);
                $updated[] = 'apt';
            } catch (Throwable $e) {
                $failed['apt'] = $e->getMessage();
            }
        }

        if ($updated !== []) {
            Cache::put(self::CACHE_KEY_RELEASES, [
                'fetchedAt'  => now()->toIso8601String(),
                'components' => $components,
                'apt'        => $apt,
            ], now()->addDays((int) config('superadmin.version_policy.release_cache_days', 3)));
        }

        return compact('updated', 'failed');
    }

    /**
     * apt ile yönetilen bileşenin değerlendirmesi. Bulgular toplanır; durum en kötü
     * bulgu, mesaj hepsinin birleşimidir (ör. "destek bitti" + "paket listesi eski").
     *
     * @param  array{name: string, installed: string, candidate: ?string, upgradable: bool, origin: string, source: ?string}  $package
     * @param  array{cycle: string, latest: ?string, eol: ?string}|null  $cycle
     * @return array<string, mixed>
     */
    private function checkAptPackage(
        string $version,
        array $rule,
        array $package,
        ?string $listsUpdatedAt,
        ?array $cycle,
        ?string $latestOverall,
        CarbonImmutable $today,
        int $warnDays,
    ): array {
        $candidateUpstream = $package['candidate'] ? $this->upstreamOf($package['candidate']) : null;
        $latest = null;
        if ($package['upgradable']) {
            // Aynı upstream sürümün yeni bir paket revizyonuysa (Ubuntu güvenlik yaması) tam sürümü göster.
            $latest = $candidateUpstream !== $version ? $candidateUpstream : preg_replace('/^\d+:/', '', $package['candidate']);
        }

        $upstreamEol = $cycle['eol'] ?? $this->matchBranch($version, $rule['eol'] ?? []);
        $eol = $package['origin'] === 'ubuntu-main'
            ? (string) config('superadmin.version_policy.apt.distro_eol')
            : $upstreamEol;

        $upgradeAvailable = $latestOverall !== null && $cycle !== null
            && version_compare($latestOverall, $cycle['latest'] ?? $version, '>');

        $findings = [];
        $eolFinding = false;

        if (isset($rule['minimum']) && version_compare($version, $rule['minimum'], '<')) {
            $findings[] = [self::STATUS_CRITICAL, "Desteklenen en düşük sürüm {$rule['minimum']}; güncelleme zorunlu."];
        }

        if ($eol !== null && $eol !== '') {
            $eolDate = CarbonImmutable::parse($eol);
            $newest = $latestOverall ? " (en yeni: {$latestOverall})" : '';
            if ($today->greaterThan($eolDate)) {
                $findings[] = $package['origin'] === 'ubuntu-universe'
                    ? [self::STATUS_WARNING, "Sürüm dalının resmi desteği {$eolDate->format('d.m.Y')} tarihinde bitti. Ubuntu universe paketi olarak güvenlik yaması alıyor ama garantisi yok (garanti Ubuntu Pro/ESM ile); yeni dala geçiş planlanmalı{$newest}."]
                    : [self::STATUS_CRITICAL, "Güvenlik desteği {$eolDate->format('d.m.Y')} tarihinde bitti; yeni bir sürüm dalına geçilmeli{$newest}."];
                $eolFinding = true;
            } elseif (($daysLeft = (int) $today->diffInDays($eolDate)) <= $warnDays) {
                $findings[] = [self::STATUS_WARNING, "Güvenlik desteği {$daysLeft} gün sonra bitiyor ({$eolDate->format('d.m.Y')})."];
            }
        }

        if ($package['upgradable']) {
            $findings[] = [self::STATUS_WARNING, "Paket güncellemesi mevcut: {$latest} (apt)."];
        }

        // Destek bitti bulgusu zaten dal değişikliği istiyor; "önerilen sürüm" tekrarı eklenmez.
        if (! $eolFinding && isset($rule['recommended']) && version_compare($version, $rule['recommended'], '<')) {
            $findings[] = [self::STATUS_WARNING, "Önerilen sürüm {$rule['recommended']} veya üstü."];
        }

        $staleAfter = (int) config('superadmin.version_policy.apt.lists_stale_days', 14);
        $staleDays = $listsUpdatedAt ? (int) CarbonImmutable::parse($listsUpdatedAt)->diffInDays($today) : null;
        $listsStale = $staleDays !== null && $staleDays > $staleAfter;
        if ($listsStale) {
            $findings[] = [self::STATUS_WARNING, "Paket listeleri {$staleDays} gündür yenilenmemiş (`apt update` çalışmıyor); yeni yamalar burada görünmüyor olabilir."];
        }

        if ($findings === []) {
            $message = 'Paket deposundaki en son sürüm.';
            // Üçüncü taraf depo (ör. CloudPanel) resmi yamayı henüz yayınlamamış olabilir — bilgi amaçlı.
            if ($package['origin'] === 'third-party' && isset($cycle['latest']) && version_compare($cycle['latest'], $version, '>')) {
                $message .= " Resmi son yama {$cycle['latest']}; paket deposuna henüz gelmedi.";
            } elseif ($upgradeAvailable) {
                $message .= " Yeni sürüm dalı mevcut: {$latestOverall}.";
            }
            $findings[] = [self::STATUS_OK, $message];
        }

        return $this->result(
            $version,
            $this->worst(array_column($findings, 0)),
            implode(' ', array_column($findings, 1)),
            [
                'eol'              => $eol ?: null,
                'latest'           => $latest,
                'latestOverall'    => $latestOverall,
                'updateAvailable'  => $package['upgradable'],
                'upgradeAvailable' => $upgradeAvailable,
                'managedBy'        => 'apt',
                'package'          => $package['name'],
                'packageSource'    => $this->aptSourceLabel($package),
                'aptListsStale'    => $listsStale,
                'branchChangeNeeded' => $eolFinding,
            ],
        );
    }

    /**
     * Kartta gösterilecek "nasıl güncellenir" komutu ve notu. Panel bu komutu
     * ÇALIŞTIRMAZ — sadece kopyalanmak üzere gösterir (sistem paketleri root ister,
     * composer/npm canlı dizinde test edilmeden çalıştırılmamalı).
     *
     * @param  array<string, mixed>  $check  checkOne() sonucu
     * @param  array{composer?: string, npm?: string}  $rule
     * @return array{updateCommand: ?string, updateNote: ?string}
     */
    public function updateInstructions(array $check, array $rule): array
    {
        $command = null;
        $notes = [];

        if ($check['managedBy'] === 'apt') {
            if ($check['updateAvailable']) {
                $command = "sudo apt update && sudo apt install --only-upgrade {$check['package']}";
            } elseif ($check['aptListsStale']) {
                $command = 'sudo apt update && apt list --upgradable';
                $notes[] = 'Önce paket listelerini yenileyin; kart bir sonraki haftalık kontrolde (ya da `php artisan superadmin:check-versions` ile hemen) güncellenir.';
            }
        } elseif ($check['updateAvailable'] && isset($rule['composer'])) {
            $command = "composer update {$rule['composer']} --with-dependencies";
            $notes[] = 'Canlı dizinde doğrudan çalıştırmayın; önce ayrı bir kopyada testleri çalıştırın.';
        } elseif ($check['updateAvailable'] && isset($rule['npm'])) {
            $command = "npm update {$rule['npm']}";
            $notes[] = 'Ardından build alınmalı.';
        }

        if ($check['branchChangeNeeded']) {
            $notes[] = 'Yeni sürüm dalına geçiş tek komutla yapılamaz (farklı paket deposu, veri/uyumluluk kontrolü); ayrı bir geçiş planı gerekir.';
        }

        return [
            'updateCommand' => $command,
            'updateNote'    => $notes === [] ? null : implode(' ', $notes),
        ];
    }

    /**
     * Çalışan sürüme karşılık gelen kurulu apt paketi; paket yoksa ya da kurulu
     * paket sürümü çalışan sürümle eşleşmiyorsa (elle derlenmiş vb.) null.
     *
     * @param  array{packages?: array<string, array<string, mixed>>}|null  $apt
     */
    private function aptPackageFor(string $version, ?string $template, ?array $apt): ?array
    {
        if ($template === null || empty($apt['packages'])) {
            return null;
        }

        $parts = explode('.', $version);
        $name = str_replace(
            ['{major}', '{minor}'],
            [$parts[0], $parts[0].'.'.($parts[1] ?? '0')],
            $template,
        );
        $package = $apt['packages'][$name] ?? null;

        if ($package === null || empty($package['installed']) || $this->upstreamOf($package['installed']) !== $version) {
            return null;
        }

        return $package + ['name' => $name];
    }

    /** Debian sürümünün upstream kısmı: "5:7.0.15-1ubuntu0.24.04.4" → "7.0.15", "1.30.4-1+clp-noble" → "1.30.4". */
    private function upstreamOf(string $debianVersion): string
    {
        return preg_match('/^(?:\d+:)?([^-]+)/', $debianVersion, $m) ? $m[1] : $debianVersion;
    }

    private function aptSourceLabel(array $package): string
    {
        return match ($package['origin']) {
            'third-party' => 'Üçüncü taraf depo'.($package['source'] ? " ({$package['source']})" : ''),
            default       => 'Ubuntu '.substr($package['origin'], strlen('ubuntu-')),
        };
    }

    /**
     * Kurulu sistem paketlerinin kurulu/aday sürümlerini ve deposunu okur.
     * Sadece okuma yapar (dpkg-query / apt-cache / dpkg --compare-versions) —
     * root gerektirmez, hiçbir şey kurmaz. Aday sürümler son `apt update`'e göredir.
     *
     * @param  list<string>  $templates  config'teki paket adı şablonları
     * @return array{listsUpdatedAt: ?string, packages: array<string, array{installed: string, candidate: ?string, upgradable: bool, origin: string, source: ?string}>}
     */
    private function fetchAptPackages(array $templates): array
    {
        $globs = array_values(array_unique(array_map(
            fn (string $t) => str_replace(['{major}', '{minor}'], '*', $t),
            $templates,
        )));

        // dpkg-query eşleşmeyen desen olunca sıfır dışı çıkar ama bulduklarını yine yazar.
        $list = Process::run(array_merge(['dpkg-query', '-W', '-f=${Package}\t${db:Status-Abbrev}\n'], $globs));
        $names = [];
        foreach (explode("\n", $list->output()) as $line) {
            [$name, $status] = array_pad(explode("\t", trim($line)), 2, '');
            if ($name !== '' && str_starts_with($status, 'ii')) {
                $names[] = $name;
            }
        }
        if ($names === []) {
            throw new \RuntimeException('Kurulu apt paketi bulunamadı (dpkg yok ya da bileşenler apt ile kurulmamış).');
        }

        $policy = Process::run(array_merge(['apt-cache', 'policy'], $names));
        if (! $policy->successful()) {
            throw new \RuntimeException('apt-cache policy başarısız: '.trim($policy->errorOutput()));
        }

        $packages = [];
        foreach ($this->parseAptPolicy($policy->output()) as $name => $p) {
            if ($p['installed'] === null) {
                continue;
            }
            $p['upgradable'] = $p['candidate'] !== null && $p['candidate'] !== $p['installed']
                && Process::run(['dpkg', '--compare-versions', $p['candidate'], 'gt', $p['installed']])->successful();
            $packages[$name] = $p;
        }

        return [
            'listsUpdatedAt' => $this->aptListsUpdatedAt(),
            'packages'       => $packages,
        ];
    }

    /**
     * `apt-cache policy` çıktısını ayrıştırır; aday sürümün hangi depodan geldiğini
     * (Ubuntu main/universe ya da üçüncü taraf) sürüm tablosundan bulur.
     *
     * @return array<string, array{installed: ?string, candidate: ?string, origin: string, source: ?string}>
     */
    public function parseAptPolicy(string $output): array
    {
        $packages = [];
        $current = null;
        $tableVersion = null;

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+):$/', $line, $m)) {
                $current = $m[1];
                $tableVersion = null;
                $packages[$current] = ['installed' => null, 'candidate' => null, 'origin' => 'third-party', 'source' => null];
                $originFound = false;

                continue;
            }
            if ($current === null) {
                continue;
            }

            if (preg_match('/^\s+(Installed|Candidate):\s+(\S+)/', $line, $m)) {
                $packages[$current][strtolower($m[1])] = $m[2] === '(none)' ? null : $m[2];
            } elseif (preg_match('/^\s+(?:\*\*\*\s+)?(\S+)\s+-?\d+$/', $line, $m)) {
                $tableVersion = $m[1];
            } elseif (
                ! $originFound
                && $tableVersion !== null
                && $tableVersion === $packages[$current]['candidate']
                && preg_match('/^\s+-?\d+\s+(\S+)\s+(\S+)/', $line, $m)
                && $m[1] !== '/var/lib/dpkg/status'
            ) {
                $host = (string) parse_url($m[1], PHP_URL_HOST);
                if (str_ends_with($host, 'ubuntu.com')) {
                    $packages[$current]['origin'] = 'ubuntu-'.(explode('/', $m[2])[1] ?? 'main');
                    $packages[$current]['source'] = $m[2];
                } else {
                    $packages[$current]['source'] = $host ?: $m[1];
                }
                $originFound = true;
            }
        }

        return $packages;
    }

    /** Paket listelerinin son yenilenme zamanı (listedeki en yeni dosya). */
    private function aptListsUpdatedAt(): ?string
    {
        $latest = 0;
        foreach (glob(rtrim((string) config('superadmin.version_policy.apt.lists_path'), '/').'/*') ?: [] as $file) {
            if (is_file($file)) {
                $latest = max($latest, (int) filemtime($file));
            }
        }

        return $latest > 0 ? CarbonImmutable::createFromTimestamp($latest)->toIso8601String() : null;
    }

    /** @return array{fetchedAt?: string, components?: array<string, list<array{cycle: string, latest: ?string, eol: ?string}>>} */
    public function cachedReleases(): array
    {
        return (array) Cache::get(self::CACHE_KEY_RELEASES, []);
    }

    /**
     * Ham metinden ilk sürüm numarasını çıkarır: "PostgreSQL 16.14" → "16.14",
     * "nginx/1.30.4" → "1.30.4", "v2.0.24" → "2.0.24". Bulunamazsa / "—" ise null.
     */
    public function normalize(?string $raw): ?string
    {
        if ($raw === null || ! preg_match('/(\d+(?:\.\d+)*)/', $raw, $m)) {
            return null;
        }

        return $m[1];
    }

    /** @return list<array{cycle: string, latest: ?string, eol: ?string}> */
    private function fetchEndOfLife(string $product): array
    {
        $rows = Http::timeout(self::HTTP_TIMEOUT)->acceptJson()
            ->get("https://endoflife.date/api/{$product}.json")
            ->throw()
            ->json();

        if (! is_array($rows) || $rows === []) {
            throw new \RuntimeException("endoflife.date: '{$product}' için veri yok.");
        }

        // `eol` alanı tarih, true (bitti, tarihsiz) veya false (sürüyor) olabilir.
        return array_values(array_map(fn (array $r) => [
            'cycle'  => (string) $r['cycle'],
            'latest' => isset($r['latest']) ? (string) $r['latest'] : null,
            'eol'    => is_string($r['eol'] ?? null) ? $r['eol'] : (($r['eol'] ?? false) === true ? '1970-01-01' : null),
        ], $rows));
    }

    /**
     * Packagist'te EOL bilgisi yok; dallar major sürüme göre kurulur.
     *
     * @return list<array{cycle: string, latest: ?string, eol: ?string}>
     */
    private function fetchPackagist(string $package): array
    {
        $data = Http::timeout(self::HTTP_TIMEOUT)->acceptJson()
            ->get("https://repo.packagist.org/p2/{$package}.json")
            ->throw()
            ->json();

        $latestByMajor = [];
        // Minified formatta her kayıtta `version` her zaman bulunur (değişen alan).
        foreach ($data['packages'][$package] ?? [] as $release) {
            $v = ltrim((string) ($release['version'] ?? ''), 'v');
            if (! preg_match('/^\d+(\.\d+)*$/', $v)) {
                continue; // dev/alpha/beta/RC atlanır
            }
            $major = explode('.', $v)[0];
            if (! isset($latestByMajor[$major]) || version_compare($v, $latestByMajor[$major], '>')) {
                $latestByMajor[$major] = $v;
            }
        }

        if ($latestByMajor === []) {
            throw new \RuntimeException("Packagist: '{$package}' için kararlı sürüm yok.");
        }

        krsort($latestByMajor, SORT_NUMERIC);

        return array_values(array_map(
            fn ($major, $latest) => ['cycle' => (string) $major, 'latest' => $latest, 'eol' => null],
            array_keys($latestByMajor),
            $latestByMajor,
        ));
    }

    /**
     * @param  list<array{cycle: string, latest: ?string, eol: ?string}>  $cycles
     * @return array{cycle: string, latest: ?string, eol: ?string}|null
     */
    private function matchCycle(string $version, array $cycles): ?array
    {
        $byBranch = [];
        foreach ($cycles as $c) {
            $byBranch[$c['cycle']] = $c;
        }
        $branch = $this->matchBranch($version, array_combine(array_keys($byBranch), array_keys($byBranch)));

        return $branch !== null ? $byBranch[$branch] : null;
    }

    /** @param  list<array{cycle: string, latest: ?string, eol: ?string}>  $cycles */
    private function latestOverall(array $cycles): ?string
    {
        $max = null;
        foreach ($cycles as $c) {
            if ($c['latest'] !== null && ($max === null || version_compare($c['latest'], $max, '>'))) {
                $max = $c['latest'];
            }
        }

        return $max;
    }

    /**
     * Sürüme uyan en spesifik dalın değerini bulur: "8.3.12" için "8.3" anahtarı,
     * "16.14" için "16" anahtarı. "1" anahtarı "16.x"e yanlışlıkla eşleşmez.
     *
     * @param  array<string, string>  $table
     */
    private function matchBranch(string $version, array $table): ?string
    {
        $best = null;
        $bestLen = -1;
        foreach ($table as $branch => $value) {
            $branch = (string) $branch;
            $matches = $version === $branch || str_starts_with($version, $branch.'.');
            if ($matches && strlen($branch) > $bestLen) {
                $best = $value;
                $bestLen = strlen($branch);
            }
        }

        return $best;
    }

    /** @param  list<string>  $statuses */
    private function worst(array $statuses): string
    {
        $worst = self::STATUS_OK;
        foreach ($statuses as $status) {
            if (self::SEVERITY[$status] > self::SEVERITY[$worst]) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /** @return array<string, mixed> */
    private function result(?string $version, string $status, string $message, array $extra = []): array
    {
        return [
            'version'          => $version,
            'status'           => $status,
            'message'          => $message,
            'eol'              => $extra['eol'] ?? null,
            'latest'           => $extra['latest'] ?? null,
            'latestOverall'    => $extra['latestOverall'] ?? null,
            'updateAvailable'  => $extra['updateAvailable'] ?? false,
            'upgradeAvailable' => $extra['upgradeAvailable'] ?? false,
            'managedBy'        => $extra['managedBy'] ?? null,
            'package'          => $extra['package'] ?? null,
            'packageSource'    => $extra['packageSource'] ?? null,
            'aptListsStale'    => $extra['aptListsStale'] ?? false,
            'branchChangeNeeded' => $extra['branchChangeNeeded'] ?? false,
        ];
    }
}
