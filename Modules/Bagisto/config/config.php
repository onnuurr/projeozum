<?php

return [
    'name' => 'Bagisto',

    /*
    |--------------------------------------------------------------------------
    | Bagisto Sync (Faz 1: outgoing ürün + stok)
    |--------------------------------------------------------------------------
    |
    | base_url: Bagisto mağazasının kökü (ör. https://magaza.example.com).
    | webhook_secret: Webkul\SaasSync paketindeki `SAAS_SYNC_WEBHOOK_SECRET`
    | ile birebir aynı olmalı — istek gövdesi bu anahtarla HMAC-SHA256
    | imzalanır (`X-Saas-Signature` header'ı, bkz. BagistoSyncClient).
    |
    */
    'sync' => [
        'base_url'       => env('BAGISTO_SYNC_BASE_URL'),
        'webhook_secret' => env('BAGISTO_SYNC_WEBHOOK_SECRET'),
        'timeout'        => (int) env('BAGISTO_SYNC_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bagisto -> SaaS yönü (Faz 2: sipariş/iade bildirimi)
    |--------------------------------------------------------------------------
    |
    | Bagisto tarafındaki `Webkul\SaasSync` paketinin `PushEventToSaas` job'ı bu
    | değerle eşleşen bir Bearer token (`SAAS_SYNC_API_KEY`) ile
    | `{SAAS_SYNC_API_URL}/webhooks/bagisto` adresine POST atar.
    |
    */
    'inbound' => [
        'token' => env('BAGISTO_INBOUND_TOKEN'),
    ],
];
