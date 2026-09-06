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
        'disk' => env('ATELIER_CONCEPT_DISK', env('MEDIA_DISK', 'public')),
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
        // Raster çok-sayfalı taramaların otomatik vektörleştirmesi uzun sürebilir.
        'timeout'     => (int) env('ATELIER_CONVERSION_TIMEOUT', 900),
        'disk'        => env('ATELIER_CONVERSION_DISK', env('MEDIA_DISK', 'public')),
        'pdf_dir'     => 'atelier/conversions/pdf',
        'dxf_dir'     => 'atelier/conversions/dxf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Linkten Kalıp İçe Aktarma
    |--------------------------------------------------------------------------
    | "Link ile yükle" — tedarikçi ürün sayfasından PDF + önizleme görselini
    | otomatik bulup indirir (bkz. PatternUrlImportService). SSRF önleme: yalnızca
    | burada listelenen (alt-)domainlerden indirim yapılır, https zorunludur.
    */
    'pattern_import' => [
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ATELIER_PATTERN_IMPORT_HOSTS', 'vikroim.ru'))
        ))),
        'timeout' => (int) env('ATELIER_PATTERN_IMPORT_TIMEOUT', 20),
        // vikroim.ru gibi kaynaklarda PDF, vk.com/vk.ru'daki bir Mail.ru Cloud
        // görüntüleyiciye yönleniyor; gerçek dosya yalnızca JS ile "Dosyayı indir"
        // tıklaması sonrası oluşuyor (bkz. resolvers/vk-doc/resolve.mjs, izole
        // Playwright/Chromium alt-projesi — ana Vite derlemesinden bağımsız).
        'headless_timeout' => (int) env('ATELIER_PATTERN_IMPORT_HEADLESS_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kalıp Metadata AI Çıkarımı (PİLOT — yalnızca product_type + size_range)
    |--------------------------------------------------------------------------
    | Bilinmeyen satıcı formatlarında (profil eşleşmedi/genel çıkarım) ürün tipi
    | ve beden aralığını PDF kapak sayfasının görselinden Gemini Vision ile
    | tahmin eder. Ham çıktı HER ZAMAN patterns.ai_metadata'da (versioned zarf)
    | saklanır; gerçek kolonlara yalnızca confidence_threshold'u geçen alanlar
    | yazılır (bkz. Modules/Atelier/Services/PatternMetadataRuleEngine). Hata/
    | düşük güven → sessizce atlanır, çıkarım akışını bozmaz.
    */
    'pattern_metadata_ai' => [
        // gemini | mock
        'driver' => env('ATELIER_PATTERN_METADATA_AI_DRIVER', 'gemini'),
        'confidence_threshold' => (float) env('ATELIER_PATTERN_METADATA_AI_THRESHOLD', 0.6),
        'prompt_version' => 1,
        // Kapalı sözlük — mevcut satıcı profillerinin (ruslan/nipnaps) lexicon'larıyla
        // ve gözlenen örneklerle tutarlı. Yeni bir kategori sık çıkarsa buraya eklenir.
        'product_type_enum' => [
            'tulum', 'ceket', 'pantolon', 'elbise', 'kapüşonlu', 'tişört',
            'etek', 'şort', 'üst', 'gömlek', 'palto',
        ],
    ],
];
