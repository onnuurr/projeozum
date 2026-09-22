<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi Portalı'nın (self-servis) ve ona bağlı pazaryeri senkron altyapısının
 * kaldırılmasının 2. aşaması (bkz. CLAUDE.md "Veritabanı değişiklik disiplini" §4
 * kademeli silme deseni ve
 * docs/superpowers/specs/2026-09-22-bayi-portali-kaldirma-etki-analizi.md).
 *
 * Aşama 1 (tamamlandı, bu migration'dan önce): kod kaldırma + pg_dump yedeği
 * (storage/app/db-backups/portal-removal-backup-20260922.sql).
 * Aşama 2 (bu migration): tabloları `_deprecated_20260922_` önekiyle karantinaya
 * al — veri kaybı yok, geri dönülebilir, hiçbir kod artık bu tablolara erişmiyor.
 * Aşama 3 (ayrı, birkaç sürüm sonra): gerçek dropIfExists.
 */
return new class extends Migration
{
    private const PREFIX = 'deprecated_20260922_';

    private array $tables = [
        'tenant_marketplace_credentials',
        'marketplace_sales',
        'marketplace_expenses',
        'marketplace_sync_logs',
        'marketplace_commission_rates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::rename($table, self::PREFIX.$table);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable(self::PREFIX.$table)) {
                Schema::rename(self::PREFIX.$table, $table);
            }
        }
    }
};
