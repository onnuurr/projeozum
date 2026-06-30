<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `order_status_history` orphan tablosunu KARANTİNAYA alır.
 *
 * Eski sipariş-durum geçmişi özelliğinden (2026_05_27_100000_create_order_status_history_table)
 * kalmıştır: repo'da artık ne migration'ı ne de Eloquent modeli vardır, kodda referansı yoktur.
 * `2026_06_09_120000_quarantine_legacy_orphan_tables` listesine dahil edilmemişti; bu migration
 * onu aynı `_deprecated_20260609` karantina desenine ekler.
 *
 * Silmek yerine yeniden adlandırır (veri kaybolmaz, geri alınabilir). Gerçek DROP birkaç sürüm
 * sonra ayrı bir migration ile yapılacaktır. Temiz bir DB'de tablo bulunmayacağı için işlem
 * `Schema::hasTable()` ile korunur (idempotent).
 */
return new class extends Migration
{
    private const TABLE = 'order_status_history';
    private const QUARANTINED = 'order_status_history_deprecated_20260609';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE) && ! Schema::hasTable(self::QUARANTINED)) {
            Schema::rename(self::TABLE, self::QUARANTINED);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::QUARANTINED) && ! Schema::hasTable(self::TABLE)) {
            Schema::rename(self::QUARANTINED, self::TABLE);
        }
    }
};
