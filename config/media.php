<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Medya Diski
    |--------------------------------------------------------------------------
    |
    | Tüm modüllerin (Product, Creative, Atelier) görsel/dosya çıktılarının
    | yazılıp okunacağı disk. Local'de 'public' (storage/app/public →
    | http://localhost/storage). Canlıda Cloudflare R2/CDN için .env'de
    | MEDIA_DISK=s3 yapılır; URL'ler App\Support\Media::url() ile çözülür.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

];
