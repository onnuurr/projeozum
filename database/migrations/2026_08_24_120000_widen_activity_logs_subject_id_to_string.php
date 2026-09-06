<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `activity_logs.subject_id` bigint idi — yalnızca auto-increment PK'li
 * modeller için doğruydu. UUID/ULID PK'li modeller (ör. Laravel'in kendi
 * `DatabaseNotification`'ı) global aktivite logger'ında (bkz.
 * app/Listeners/LogModelActivity.php, AppServiceProvider'daki 'eloquent.*: *'
 * joker event'leri) bu koloniya UUID string yazmaya çalışınca Postgres
 * "invalid input syntax for type bigint" ile patlıyor — 500 hatası
 * (bildirim ikonuna basınca/okundu işaretlerken gözlenen hata).
 *
 * doctrine/dbal kurulu değil (Blueprint::change() kullanılamaz) → ham SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            // sqlite (Pest testleri) zaten dinamik tipli — gerçek ALTER anlamsız, no-op.
            return;
        }

        DB::statement('ALTER TABLE activity_logs ALTER COLUMN subject_id TYPE VARCHAR(191) USING subject_id::VARCHAR');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // UUID gibi bigint'e sığmayan değerler zaten yazılmışsa bu rollback başarısız
        // olur — beklenen davranış (veri kaybını sessizce kabul etmeyiz).
        DB::statement('ALTER TABLE activity_logs ALTER COLUMN subject_id TYPE BIGINT USING subject_id::BIGINT');
    }
};
