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
];
