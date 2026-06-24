<?php

return [
    'name' => 'Atelier',

    /*
    |--------------------------------------------------------------------------
    | AI Konsept Stüdyosu (Kalıp platformu — Kol 2)
    |--------------------------------------------------------------------------
    | Tarif → görsel konsept üretimi. Model-agnostik: contract sabit kalır,
    | sürücü config ile değişir. 'gemini' anahtarı yoksa otomatik 'mock'a düşülür
    | (anahtarsız dev/test için placeholder üretir). Gemini kimlik bilgileri
    | Creative modülünün 'creative.ai.gemini.*' ayarından ödünç alınır — env'de
    | GEMINI_API_KEY zaten tanımlı.
    |
    | Telif/güvenlik: konsept üretimi yalnızca kendi tarif havuzundan metinle
    | çalışır; marka ürün fotoğrafı referans verilmez (yol haritası §3, §8).
    */
    'concept' => [
        // gemini | mock
        'driver' => env('ATELIER_CONCEPT_DRIVER', 'gemini'),
        // Tek tarifte üretilecek varyant sayısı (kullanıcı 1..max arası seçer).
        'max_variants' => (int) env('ATELIER_CONCEPT_MAX_VARIANTS', 4),
        // Üretilen konsept görsellerinin saklandığı disk ve dizin.
        'disk' => env('ATELIER_CONCEPT_DISK', 'public'),
        'dir'  => 'atelier/concepts',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF→DXF Sayısallaştırma (Kalıp platformu — Kol 1)
    |--------------------------------------------------------------------------
    | Ağır dönüştürme ayrı bir Python/FastAPI mikroservisinde yapılır (PyMuPDF +
    | ezdxf). Laravel PDF'i HTTP ile gönderir, kuyrukla asenkron işler. Servis
    | URL'i yoksa veya driver=mock ise sahte sonuç döner (servissiz dev/test).
    |
    | Çıktı her zaman insan onay kuyruğuna düşer (yol haritası §2.4): sınıflandırma
    | (yeşil/sarı/kırmızı) yalnızca triyaj rengidir, otomatik onay değildir.
    */
    'conversion' => [
        // http | mock
        'driver'      => env('ATELIER_CONVERSION_DRIVER', 'http'),
        'service_url' => env('ATELIER_CONVERSION_URL', 'http://127.0.0.1:8200'),
        'timeout'     => (int) env('ATELIER_CONVERSION_TIMEOUT', 300),
        'disk'        => env('ATELIER_CONVERSION_DISK', 'public'),
        'pdf_dir'     => 'atelier/conversions/pdf',
        'dxf_dir'     => 'atelier/conversions/dxf',
    ],
];
