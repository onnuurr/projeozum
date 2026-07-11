<?php

return [
    'name' => 'Marketplace',

    /*
    |--------------------------------------------------------------------------
    | Marketplace Integration
    |--------------------------------------------------------------------------
    |
    | driver: 'stub' (dev/CI fixture-based) | 'live' (gerçek API).
    | Per-provider config (base_url, timeout, rate-limit). Her provider'ın
    | adapter'ı bu config'i okur (config('marketplace.<provider>.*')).
    |
    | Not: TENANT_MARKETPLACE_DRIVER env anahtarı geriye-dönük uyumluluk için
    | destekleniyor; yeni deployment'lar MARKETPLACE_DRIVER kullanmalı.
    |
    */
    'driver' => env('MARKETPLACE_DRIVER', env('TENANT_MARKETPLACE_DRIVER', 'stub')),

    'trendyol' => [
        'base_url'   => env('TRENDYOL_BASE_URL', 'https://api.trendyol.com'),
        'timeout'    => (int) env('TRENDYOL_TIMEOUT', 30),
        'rate_limit' => ['rps' => (int) env('TRENDYOL_RPS', 10)],
    ],
    'hepsiburada' => [
        'base_url'   => env('HEPSIBURADA_BASE_URL', 'https://mpop.hepsiburada.com'),
        'timeout'    => (int) env('HEPSIBURADA_TIMEOUT', 30),
        'rate_limit' => ['rps' => (int) env('HEPSIBURADA_RPS', 5)],
    ],
    'n11' => [
        'base_url'   => env('N11_BASE_URL', 'https://api.n11.com'),
        'timeout'    => (int) env('N11_TIMEOUT', 30),
        'rate_limit' => ['rps' => (int) env('N11_RPS', 5)],
    ],
    'ciceksepeti' => [
        'base_url'   => env('CICEKSEPETI_BASE_URL', 'https://api.ciceksepeti.com'),
        'timeout'    => (int) env('CICEKSEPETI_TIMEOUT', 30),
        'rate_limit' => ['rps' => (int) env('CICEKSEPETI_RPS', 3)],
    ],
];
