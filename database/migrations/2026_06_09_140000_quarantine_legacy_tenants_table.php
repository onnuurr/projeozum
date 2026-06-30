<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eski kuşak `tenants` tablosunu KARANTİNAYA alır.
 *
 * DB'deki canlı `tenants` tablosu eski şemadadır (slug, category, status, plan, settings) ve
 * `Modules/Tenant` modülünün modeli/migration'larının beklediği YENİ şemayla (code, legal_name,
 * tenant_type_id, credit_limit, payment_term_days, is_active …) uyumsuzdur. Bu yüzden Tenant
 * modülünün Pending migration'ları bu DB'ye karşı çalışamıyor (isim çakışması).
 *
 * Tabloyu `tenants_deprecated_20260609` olarak yeniden adlandırır (veri korunur, geri alınabilir).
 * Yeni `tenants` tablosu aynı adla yeniden oluşturulacağı için, PostgreSQL'in tablo adı
 * değiştirildiğinde KORUDUĞU ve yeni tabloyla ÇAKIŞACAK objeler de yeniden adlandırılır:
 *   - index/PK : tenants_pkey
 *   - sequence : tenants_id_seq
 *   - not-null : tenants_id_not_null, tenants_name_not_null   (PG17+ adlandırılmış constraint)
 *   - unique   : tenants_slug_unique  (yeni şemada `add_plan_columns` migration'ı slug'ı geri
 *                ekleyip tekrar unique yapıyor → çakışır), tenants_email_unique (tutarlılık için)
 *
 * Check constraint'leri (category/status) yeni şemada üretilmediği için çakışmaz; bırakılır.
 */
return new class extends Migration
{
    private const OLD = 'tenants';
    private const NEW = 'tenants_deprecated_20260609';

    public function up(): void
    {
        // Yalnızca ESKİ şema tenants tablosunda çalış. Ayırt edici: eski şemada `category` kolonu
        // vardır, YENİ şemada yoktur. Bu sayede sıfırdan kurulan (test/CI/prod-sonrası) temiz bir
        // DB'de — `tenants` yeni şemayla zaten oluşturulmuş olsa bile — bu migration NO-OP olur ve
        // aşağıdaki PostgreSQL'e özgü ALTER ifadeleri hiç çalışmaz.
        if (! Schema::hasTable(self::OLD)
            || Schema::hasTable(self::NEW)
            || ! Schema::hasColumn(self::OLD, 'category')) {
            return;
        }

        Schema::rename(self::OLD, self::NEW);

        DB::statement('ALTER INDEX tenants_pkey RENAME TO tenants_deprecated_20260609_pkey');
        DB::statement('ALTER INDEX tenants_slug_unique RENAME TO tenants_deprecated_20260609_slug_unique');
        DB::statement('ALTER INDEX tenants_email_unique RENAME TO tenants_deprecated_20260609_email_unique');
        DB::statement('ALTER SEQUENCE tenants_id_seq RENAME TO tenants_deprecated_20260609_id_seq');
        DB::statement('ALTER TABLE tenants_deprecated_20260609 RENAME CONSTRAINT tenants_id_not_null TO tenants_deprecated_20260609_id_not_null');
        DB::statement('ALTER TABLE tenants_deprecated_20260609 RENAME CONSTRAINT tenants_name_not_null TO tenants_deprecated_20260609_name_not_null');
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::NEW) || Schema::hasTable(self::OLD)) {
            return;
        }

        DB::statement('ALTER TABLE tenants_deprecated_20260609 RENAME CONSTRAINT tenants_deprecated_20260609_name_not_null TO tenants_name_not_null');
        DB::statement('ALTER TABLE tenants_deprecated_20260609 RENAME CONSTRAINT tenants_deprecated_20260609_id_not_null TO tenants_id_not_null');
        DB::statement('ALTER SEQUENCE tenants_deprecated_20260609_id_seq RENAME TO tenants_id_seq');
        DB::statement('ALTER INDEX tenants_deprecated_20260609_email_unique RENAME TO tenants_email_unique');
        DB::statement('ALTER INDEX tenants_deprecated_20260609_slug_unique RENAME TO tenants_slug_unique');
        DB::statement('ALTER INDEX tenants_deprecated_20260609_pkey RENAME TO tenants_pkey');

        Schema::rename(self::NEW, self::OLD);
    }
};
