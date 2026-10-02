<?php

return [
    'name' => 'Superadmin',

    /*
    |--------------------------------------------------------------------------
    | Otomatik yedekleme (backup:run)
    |--------------------------------------------------------------------------
    | pg_dump + proje dosyaları tar'ı alınıp rclone ile uzak depolamaya (Google
    | Drive) kopyalanır. DB kimlik bilgileri buradan DEĞİL, config('database')
    | üzerinden okunur (bkz. BackupService) — burada sadece uzak hedef/retention.
    */
    'backup' => [
        'rclone_remote' => env('BACKUP_RCLONE_REMOTE', 'gdrive:ServerBackup'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'temp_dir' => storage_path('app/backup-temp'),
        'exclude' => [
            'node_modules',
            'vendor',
            'bootstrap/cache/*',
            'storage/framework/cache/*',
            'storage/framework/sessions/*',
            'storage/framework/views/*',
            'storage/framework/testing',
            'storage/app/backup-temp/*',
            // /graphify skill çıktısı: yeniden üretilebilir önbellek, root olarak oluşup ozumserver'ın
            // okuyamadığı dosyalar içerebiliyor — tar hata verip TÜM yedeği düşürüyordu
            // (2026-09-09'dan 2026-09-26'ya kadar gece yedeği bu yüzden hiç alınmadı).
            'graphify-out',
            '.graphify_*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mimari Doktor — panelden otomatik düzeltme
    |--------------------------------------------------------------------------
    | "Otomatik Düzelt" butonu, sunucudaki `claude` CLI'ı headless modda izole
    | bir git worktree içinde tetikleyip .claude/agents/architecture-doctor-fixer.md
    | agent'ını çalıştırır (bkz. ArchitectureDoctorFixService). Bilinçli opt-in:
    | `enabled=false` iken MockFixerRunner bind edilir, hiçbir subprocess çalışmaz.
    */
    'architecture_doctor' => [
        'enabled' => (bool) env('ARCHITECTURE_DOCTOR_FIX_ENABLED', false),
        'claude_binary' => env('ARCHITECTURE_DOCTOR_CLAUDE_BINARY', 'claude'),
        'fix_timeout' => (int) env('ARCHITECTURE_DOCTOR_FIX_TIMEOUT', 3600),
        'worktree_base_path' => env(
            'ARCHITECTURE_DOCTOR_WORKTREE_BASE_PATH',
            dirname(base_path()).'/architecture-doctor-fix-worktrees'
        ),
        // Canlı checkout — sadece testlerde geçici bir fixture git deposuna
        // override edilir (bkz. ArchitectureDoctorFixServiceTest), üretimde
        // her zaman base_path() kalır.
        'repo_path' => base_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Yazılım sürümü politikası (Süper admin › Sistem Paneli › Yazılım sürümleri)
    |--------------------------------------------------------------------------
    | VersionCheckService kurulu sürümleri iki kaynağa göre değerlendirir:
    |
    | 1) Yayın verisi (arka planda): `superadmin:check-versions` komutu haftalık
    |    olarak her bileşenin `source`'undan son sürümleri ve EOL tarihlerini çeker,
    |    cache'e yazar. Panel isteği asla dış servise gitmez; sadece cache'i okur.
    |      endoflife → https://endoflife.date/api/<ürün>.json (dal, son sürüm, EOL)
    |      packagist → https://repo.packagist.org/p2/<paket>.json (dal = major)
    |
    | 2) Statik politika (yayın verisi yoksa/eksikse yedek):
    |      minimum     → altı "kritik" (desteklenmiyor / uygulama gereksinimi)
    |      recommended → altı "uyarı" (güncelleme önerilir)
    |      eol         → dal => güvenlik desteğinin bittiği tarih. Anahtar sürümün
    |                    başlangıcıyla eşleşir ("8.3" → 8.3.x, "16" → 16.x).
    |    EOL tarihi geçmişse "kritik", `eol_warning_days` gün içindeyse "uyarı".
    |
    | 3) Sistem paketleri (`apt`): bileşende `apt` paket adı varsa ve çalışan sürüm
    |    kurulu paketle eşleşiyorsa değerlendirme resmi sürüme göre DEĞİL, paket
    |    deposuna göre yapılır — "güncelleme mevcut" sadece `apt` gerçekten daha
    |    yeni bir sürüm sunuyorsa gösterilir. Ubuntu güvenlik düzeltmelerini eski
    |    sürüm numarasına ekler (ör. redis 7.0.15-1ubuntu0.24.04.4), bu yüzden:
    |      Ubuntu main     → destek süresi dağıtımın (`apt.distro_eol`) süresidir
    |      Ubuntu universe → resmi dal EOL'ü geçse de "uyarı" (Ubuntu yamalıyor ama
    |                        garanti etmiyor; garanti Ubuntu Pro/ESM ile)
    |      üçüncü taraf    → (ör. CloudPanel) resmi dal EOL'ü geçerli
    |    Paket adında {major} ("16") ve {minor} ("8.5") yer tutucuları kullanılabilir.
    |    Paket listeleri `apt.lists_stale_days` günden eskiyse sonuç "uyarı" olur
    |    (sunucuda `apt update` çalışmıyor demektir; aday sürümler eski olabilir).
    |
    | 4) `composer` / `npm` paket adı: kartta gösterilen "nasıl güncellenir"
    |    komutu için. Panel komutları çalıştırmaz, sadece kopyalanmak üzere gösterir.
    */
    'version_policy' => [
        'eol_warning_days' => 90,
        'release_cache_days' => 10, // haftalık kontrol + pay; komut bu süre çalışmazsa yayın verisi düşer, statik politikaya dönülür
        'apt' => [
            'distro' => 'Ubuntu 24.04',
            'distro_eol' => '2029-05-31', // Ubuntu 24.04 LTS standart destek sonu
            'lists_stale_days' => 14,
            'lists_path' => '/var/lib/apt/lists',
        ],
        'components' => [
            'php' => [
                'source' => ['endoflife' => 'php'],
                'apt' => 'php{minor}-fpm',
                'minimum' => '8.2', // Laravel 12 gereksinimi
                'recommended' => '8.3',
                'eol' => ['8.2' => '2026-12-31', '8.3' => '2027-12-31', '8.4' => '2028-12-31', '8.5' => '2029-12-31'],
            ],
            'laravel' => [
                'source' => ['endoflife' => 'laravel'],
                'composer' => 'laravel/framework',
                'minimum' => '12.0',
                'eol' => ['11' => '2026-03-12', '12' => '2027-02-24', '13' => '2028-03-17'],
            ],
            'database' => [
                'source' => ['endoflife' => 'postgresql'],
                'apt' => 'postgresql-{major}',
                'minimum' => '14',
                'recommended' => '16',
                'eol' => ['14' => '2026-11-12', '15' => '2027-11-11', '16' => '2028-11-09', '17' => '2029-11-08', '18' => '2030-11-14'],
            ],
            'redis' => [
                'source' => ['endoflife' => 'redis'],
                'apt' => 'redis-server',
                'minimum' => '6.2',
                'recommended' => '7.2',
            ],
            'webServer' => [
                'source' => ['endoflife' => 'nginx'],
                'apt' => 'nginx',
                'minimum' => '1.24',
            ],
            'inertia' => [
                'source' => ['packagist' => 'inertiajs/inertia-laravel'],
                'composer' => 'inertiajs/inertia-laravel',
                'minimum' => '2.0',
            ],
            'vue' => [
                'source' => ['endoflife' => 'vue'],
                'npm' => 'vue',
                'minimum' => '3.4',
            ],
        ],
    ],
];
