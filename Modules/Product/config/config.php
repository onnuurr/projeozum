<?php

return [
    'name' => 'Product',

    /*
    |--------------------------------------------------------------------------
    | Kargo (D6 — B2B basitleştirme)
    |--------------------------------------------------------------------------
    | standard/express/same_day yerine iki yöntem: `cargo` (sabit ücret, eşik
    | üstü ücretsiz) ve `pickup` (Depodan Teslim, 0 ₺). CheckoutService::quote()
    | ve place() bu değerleri okur; StoreDropshipOrderRequest `in:cargo,pickup`.
    */
    'shipping' => [
        'cargo_fee'            => 49.90,
        'free_shipping_target' => 500.00,
        'methods'              => [
            'cargo'  => ['label' => 'Kargo',          'description' => 'Anlaşmalı kargo ile teslim'],
            'pickup' => ['label' => 'Depodan Teslim', 'description' => 'Siparişi depodan teslim al (ücretsiz)'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI içerik otomasyonu
    |--------------------------------------------------------------------------
    | auto_seo_on_bom: Bir ürüne reçete (BOM) kaydedilince, AI ile SEO uyumlu
    | başlık (public_name) + açıklamalar + meta alanları üretip ürüne yazan
    | GenerateProductSeoContentJob'u kuyruğa alır. Testlerde kapalı tutulur.
    */
    'ai' => [
        'auto_seo_on_bom' => env('PRODUCT_AI_AUTO_SEO_ON_BOM', true),
    ],
];
