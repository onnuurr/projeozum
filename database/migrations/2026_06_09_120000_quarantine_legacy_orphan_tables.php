<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Terk edilmiş özellik kuşaklarından kalan ve hiçbir migration tarafından oluşturulmayan,
 * kodda referansı bulunmayan orphan tabloları KARANTİNAYA alır.
 *
 * Silmek yerine `<tablo>_deprecated_20260609` olarak yeniden adlandırır:
 *   - Veri kaybolmaz, geri alınabilir (down() ile geri adlandırılır).
 *   - Birkaç sürüm sonra ayrı bir migration ile gerçek DROP yapılacaktır.
 *
 * `schema:audit` ile tespit edildi. Repo migration'larından kurulan TEMİZ bir veritabanında
 * bu tablolar bulunmaz; bu yüzden her işlem `Schema::hasTable()` ile korunur (idempotent).
 */
return new class extends Migration
{
    private const SUFFIX = '_deprecated_20260609';

    /**
     * Karantinaya alınacak orphan tablolar (gruplandırılmış).
     *
     * @var string[]
     */
    private array $tables = [
        // Ads — eski manuel editör kalıntısı
        'ad_accounts', 'ad_brand_kits', 'ad_campaigns', 'ad_creative_exports',
        'ad_creatives', 'ad_generated_scenes', 'ad_templates',
        // AiStudio — eski karakter/poz/render pipeline kalıntısı
        'ai_characters', 'poses', 'ai_renders', 'render_variants',
        // Eski çok-kiracılı / cüzdan sistemi kalıntısı
        'superadmins', 'tenant_users', 'tenant_customers', 'tenant_customer_addresses',
        'tenant_allowed_categories', 'tenant_wallets', 'wallet_top_up_requests', 'wallet_transactions',
        // Diğer
        'menus', 'discount_rules',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $quarantined = $table . self::SUFFIX;

            // Zaten karantinaya alınmışsa veya tablo yoksa atla.
            if (Schema::hasTable($table) && ! Schema::hasTable($quarantined)) {
                Schema::rename($table, $quarantined);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $quarantined = $table . self::SUFFIX;

            if (Schema::hasTable($quarantined) && ! Schema::hasTable($table)) {
                Schema::rename($quarantined, $table);
            }
        }
    }
};
