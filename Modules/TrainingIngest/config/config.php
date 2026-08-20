<?php

return [
    'name' => 'TrainingIngest',

    /*
    |--------------------------------------------------------------------------
    | Etkin mi?
    |--------------------------------------------------------------------------
    | Varsayılan KAPALI (mimari doktor auto-fix'teki gibi aynı desen): training_writer/
    | training_reader Postgres rolleri ve R2 bucket'ı kurulana kadar (bkz. README.md)
    | event listener'lar hiçbir şey yapmadan çıkar. Aksi halde canlıda HER onay/ret
    | işlemi, henüz var olmayan 'training' bağlantısına bağlanmaya çalışıp kuyrukta
    | sessizce başarısız işler biriktirir — kullanıcı akışını bozmaz ama gürültü yapar.
    | Kurulum bitince .env'de TRAINING_INGEST_ENABLED=true yapılır.
    */
    'enabled' => env('TRAINING_INGEST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bağlantılar
    |--------------------------------------------------------------------------
    | 'writer'   -> training şemasına yazan bağlantı (config/database.php: 'training').
    | 'reader'   -> Creative/Product tablolarını salt-okuma okuyan bağlantı,
    |               yalnızca backfill CLI'ının kullandığı (config/database.php: 'saas_readonly').
    | Event-driven yakalama (birincil yol) 'reader' bağlantısını kullanmaz —
    | doğrudan Eloquent (varsayılan 'pgsql' bağlantısı) üzerinden Creative
    | event payload'ını okur, hiçbir zaman training şemasına o bağlantıdan yazmaz.
    */
    'connections' => [
        'writer' => 'training',
        'reader' => 'saas_readonly',
    ],

    /*
    |--------------------------------------------------------------------------
    | Depolama
    |--------------------------------------------------------------------------
    | Eğitim görselleri prod medya bucket'ından ayrı, kendi R2 bucket'ında durur.
    */
    'storage' => [
        'disk' => 'training',
    ],
];
