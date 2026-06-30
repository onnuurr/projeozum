<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Karantina yaşam döngüsünün SON adımı: `_deprecated_20260609` olarak karantinaya alınmış
 * eski özellik kuşağı tablolarını KALICI olarak siler.
 *
 * Bu tablolar `2026_06_09_120000_quarantine_legacy_orphan_tables`,
 * `..._130000_quarantine_order_status_history_table` ve `..._140000_quarantine_legacy_tenants_table`
 * tarafından karantinaya alınmıştı. Kodda referansları yok, canlı şemada kullanılmıyorlar.
 *
 * Önce engel teşkil eden tek canlı→deprecated bağımlılık temizlenir: `orders.tenant_id`. Bu kolon
 * da eski çok-kiracılı kuşaktan kalmadır (repo Product migration'larında yoktur, hiçbir model/kod
 * `orders.tenant_id` okumaz — `BelongsToTenant` trait'ini kullanan model yoktur). FK'si
 * `tenants_deprecated_20260609`'a bağlı olduğu için o tablo silinmeden kaldırılması gerekir.
 *
 * GERİ ALINAMAZ: veriler silinir. Geri yükleme `down()` ile değil, veritabanı yedeğinden yapılır
 * (kullanıcı onayı: yedek mevcut). Bu yüzden `down()` bilinçli olarak hata fırlatır.
 */
return new class extends Migration
{
    /** @var string[] Karantinadaki, kalıcı silinecek tablolar. */
    private array $tables = [
        'ad_accounts_deprecated_20260609',
        'ad_brand_kits_deprecated_20260609',
        'ad_campaigns_deprecated_20260609',
        'ad_creative_exports_deprecated_20260609',
        'ad_creatives_deprecated_20260609',
        'ad_generated_scenes_deprecated_20260609',
        'ad_templates_deprecated_20260609',
        'ai_characters_deprecated_20260609',
        'ai_renders_deprecated_20260609',
        'discount_rules_deprecated_20260609',
        'menus_deprecated_20260609',
        'order_status_history_deprecated_20260609',
        'poses_deprecated_20260609',
        'render_variants_deprecated_20260609',
        'superadmins_deprecated_20260609',
        'tenant_allowed_categories_deprecated_20260609',
        'tenant_customer_addresses_deprecated_20260609',
        'tenant_customers_deprecated_20260609',
        'tenant_users_deprecated_20260609',
        'tenant_wallets_deprecated_20260609',
        'tenants_deprecated_20260609',
        'wallet_top_up_requests_deprecated_20260609',
        'wallet_transactions_deprecated_20260609',
    ];

    public function up(): void
    {
        // 1) orders.tenant_id — eski kuşak orphan kolon; FK'si tenants_deprecated'e bağlı (engel).
        if (Schema::hasColumn('orders', 'tenant_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign('orders_tenant_id_foreign');
                $table->dropColumn('tenant_id');
            });
        }

        // 2) Karantina tablolarını düşür. Temiz bir DB'de (test/CI) bu tablolar bulunmaz → NO-OP.
        //    PostgreSQL'de aralarındaki FK bağımlılıklarını çözmek için CASCADE; diğer sürücülerde
        //    (örn. testlerdeki SQLite) standart drop — CASCADE sözdizimi desteklenmez.
        $pgsql = DB::getDriverName() === 'pgsql';
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($pgsql) {
                DB::statement("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
            } else {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Bu migration geri alınamaz: eski kuşak tabloları kalıcı olarak silmiştir. '
            . 'Geri yükleme veritabanı yedeğinden yapılmalıdır.'
        );
    }
};
