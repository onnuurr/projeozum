<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * B2C söküm (D5) — KADEMELİ tablo silme, Aşama A (karantina).
 *
 * user_addresses yalnızca B2C ana-domain checkout adres defteri içindi; tenant-only
 * B2B modelinde teslimat adresi checkout formundan gelir. Kullanan kod (CheckoutController,
 * AddressController, UserAddress modeli, Pages/Checkout.vue) bu fazda kaldırıldı.
 *
 * DROP DEĞİL — tablo `_deprecated_20260709_user_addresses` olarak karantinaya alınır;
 * gerçek dropIfExists bir-iki sürüm sonra Faz 4'te yapılır. Karantina öncesi yedek:
 *   pg_dump --table=user_addresses ... > backup_user_addresses.sql
 */
return new class extends Migration
{
    private const FROM = 'user_addresses';
    private const TO   = '_deprecated_20260709_user_addresses';

    public function up(): void
    {
        if (Schema::hasTable(self::FROM) && ! Schema::hasTable(self::TO)) {
            Schema::rename(self::FROM, self::TO);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TO) && ! Schema::hasTable(self::FROM)) {
            Schema::rename(self::TO, self::FROM);
        }
    }
};
