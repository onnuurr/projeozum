<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\TrainingIngest\Database\Migrations\Concerns\SkipsNonPostgres;

/**
 * SaaS'ın 'public' şemasına hiç dokunmaz — yalnızca yanına, aynı Postgres
 * instance'ında bağımsız bir 'training' şeması açar. Sonraki tüm TrainingIngest
 * migration'ları $connection='training' (search_path=training) ile bu şema
 * içinde tablo/view üretir. Varsayılan (SaaS) bağlantıda çalışır çünkü
 * 'training' bağlantısı şema henüz yokken search_path'i çözemez.
 */
return new class extends Migration
{
    use SkipsNonPostgres;

    public function up(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS training');
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        // Yalnızca training şeması boşsa gerçek anlamda geri alınabilir; dolu bir
        // şemayı CASCADE ile düşürmek geri dönüşü olmayan veri kaybı olur, o yüzden
        // burada bilinçli olarak yapılmaz. Gerçek rollback ihtiyacı doğarsa önce
        // pg_dump ile yedek alınıp manuel karar verilmeli (bkz. CLAUDE.md tablo
        // silme disiplini).
        DB::statement('DROP SCHEMA IF EXISTS training RESTRICT');
    }
};
