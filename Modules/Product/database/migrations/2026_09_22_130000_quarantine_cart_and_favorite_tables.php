<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sepet ve favori özelliklerinin kaldırılmasının 2. aşaması (bkz. CLAUDE.md
 * "Veritabanı değişiklik disiplini" §4 kademeli silme deseni ve
 * docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md §2).
 * Bayi Portalı kaldırmasıyla aynı dalga — bkz.
 * Modules/Tenant/database/migrations/2026_09_22_130000_quarantine_portal_and_marketplace_sync_tables.php.
 *
 * Aşama 1 (tamamlandı): kod kaldırma + pg_dump yedeği
 * (storage/app/db-backups/portal-removal-backup-20260922.sql).
 * Aşama 2 (bu migration): karantina rename, veri kaybı yok, geri dönülebilir.
 * Aşama 3 (ayrı, birkaç sürüm sonra): gerçek dropIfExists.
 */
return new class extends Migration
{
    private const PREFIX = 'deprecated_20260922_';

    private array $tables = [
        'cart_items',
        'product_favorites',
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
