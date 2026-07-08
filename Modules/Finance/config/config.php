<?php

return [
    'name' => 'Finance',

    /*
    |--------------------------------------------------------------------------
    | e-Fatura / e-Arşiv entegratörü (Trendyol e-Faturam)
    |--------------------------------------------------------------------------
    | Kimlik bilgisi (email+password) tanımlıysa gerçek Trendyol sürücüsü,
    | yoksa NullEInvoiceProvider bağlanır (bkz. FinanceServiceProvider).
    | Tutarlar API'ye kuruş (integer) gider; base URL ortama göre değişir.
    */
    'einvoice' => [
        'driver'   => env('FINANCE_EINVOICE_DRIVER', 'trendyol'),
        'base_url' => env('FINANCE_EINVOICE_BASE_URL', 'https://stage-apigateway.trendyolefaturam.com'),
        'email'    => env('FINANCE_EINVOICE_EMAIL'),
        'password' => env('FINANCE_EINVOICE_PASSWORD'),
        // companyId: iptal isteğinde ve gövdede zorunlu (token'da da mevcut olan firma id'si).
        'company_id' => env('FINANCE_EINVOICE_COMPANY_ID'),
        // Fatura kaynağı ve senaryo tipleri (Enum Değerleri sayfasıyla doğrulanmalı).
        'source'                => env('FINANCE_EINVOICE_SOURCE', 'PORTAL'),
        'einvoice_type'         => env('FINANCE_EINVOICE_TYPE', 'EFATURA'),      // mükellefe giden
        'earchive_type'         => env('FINANCE_EARCHIVE_TYPE', 'EARSIVFATURA'), // mükellef olmayana
        'invoice_type_code'     => env('FINANCE_EINVOICE_TYPE_CODE', 'SATIS'),
        // Sign-in token'ı bu kadar saniye cache'lenir (JWT ömründen kısa tutulmalı).
        'token_ttl'             => (int) env('FINANCE_EINVOICE_TOKEN_TTL', 3000),
    ],
];
