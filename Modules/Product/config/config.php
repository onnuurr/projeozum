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
];
