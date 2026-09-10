<?php

return [
    'name' => 'PatternEngine',

    /*
    |--------------------------------------------------------------------------
    | Kalıp Üretim Servisi (Python/FastAPI mikroservisi)
    |--------------------------------------------------------------------------
    | Gerçek kalıp üretimi ayrı bir Python/FastAPI servisinde (pattern-core:
    | framework bağımsız). Laravel DesignParams + beden/profil bilgisini HTTP
    | ile gönderir, kuyrukla asenkron işler. Servis URL'i yoksa veya driver=mock
    | ise sahte (Mock) sonuç döner (servissiz dev/test — Atelier'in
    | 'conversion' ayarıyla aynı desen).
    */
    'engine' => [
        // http | mock
        'driver'      => env('PATTERN_ENGINE_DRIVER', 'http'),
        'service_url' => env('PATTERN_ENGINE_URL', 'http://127.0.0.1:8201'),
        'timeout'     => (int) env('PATTERN_ENGINE_TIMEOUT', 120),
        'disk'        => env('PATTERN_ENGINE_DISK', env('MEDIA_DISK', 'public')),
        'dxf_dir'     => 'pattern-engine/dxf',
    ],
];
