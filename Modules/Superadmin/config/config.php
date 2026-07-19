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
        'rclone_remote'  => env('BACKUP_RCLONE_REMOTE', 'gdrive:ServerBackup'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'temp_dir'       => storage_path('app/backup-temp'),
        'exclude'        => [
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
];
