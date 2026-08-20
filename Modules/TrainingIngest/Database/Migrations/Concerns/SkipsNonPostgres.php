<?php

namespace Modules\TrainingIngest\Database\Migrations\Concerns;

/**
 * TrainingIngest migration'ları Postgres'e özgü sözdizimi kullanır (CREATE SCHEMA,
 * jsonb, native CHECK constraint'ler) — sqlite'ta çalışmaz. Test ortamı sqlite
 * kullandığından (phpunit.xml: DB_CONNECTION=sqlite), bu migration'lar testlerde
 * sessizce no-op olur. Modules/TrainingIngest'in kendi testleri gerçek bir Postgres
 * bağlantısı gerektirir (bkz. README "Testler" bölümü).
 */
trait SkipsNonPostgres
{
    private function runsOnPostgres(): bool
    {
        // Bilerek migration'ın KENDİ hedef bağlantısına değil, uygulamanın genel
        // varsayılan bağlantısına bakar: 'training'/'saas_readonly' driver'ı repo'da
        // sabit 'pgsql' olduğu için (doğru — canlıda hep Postgres olmalı), o alanı
        // kontrol etmek testte de hep true dönerdi. Asıl soru "bu çalışma ortamı
        // gerçekten Postgres'e mi bağlı" (prod/local) yoksa "sqlite testi mi"
        // (phpunit.xml: DB_CONNECTION=sqlite) — cevabı veren varsayılan bağlantı.
        return config('database.connections.' . config('database.default') . '.driver') === 'pgsql';
    }

    /**
     * Laravel'in Migrator'ı, Postgres gibi transactional-DDL destekleyen bağlantılarda
     * up()/down() çağrılmadan ÖNCE transaction açmak için PDO bağlantısını erkenden
     * kurar (bkz. Migrator::runMigration → $connection->transaction($callback)) — yani
     * up()'ın en başındaki runsOnPostgres() kontrolüne asla ulaşılmadan, sadece bu
     * migration'ın hedef bağlantısına (training/saas_readonly) geçilmeye çalışılır ve
     * testte (sqlite) auth hatasıyla patlar. Bu yüzden hedef bağlantı adı, gerçekten
     * Postgres'e bağlıyken normal ('training'/'saas_readonly'), değilken (test/sqlite)
     * uygulamanın varsayılan (zaten bağlı) bağlantısına düşürülür — up()/down() içindeki
     * runsOnPostgres() koruması o durumda zaten hiçbir şema işlemi yapmadan çıkar.
     */
    public function getConnection()
    {
        return $this->runsOnPostgres() ? $this->connection : null;
    }
}
