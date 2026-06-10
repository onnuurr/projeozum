<?php

return [
    'name' => 'Creative',

    /*
    |--------------------------------------------------------------------------
    | Render motoru
    |--------------------------------------------------------------------------
    | Görseller Python (Pillow + resvg) ile üretilir. PHP, payload'ı stdin'e
    | JSON olarak verir; betik PNG baytlarını stdout'a yazar.
    */
    'render' => [
        'engine'     => env('CREATIVE_RENDER_ENGINE', 'python'),
        'python_bin' => env('CREATIVE_PYTHON_BIN', 'python3'),
        // resvg CLI binary yolu. Boş bırakılırsa render.py salt-Pillow moduna
        // düşer (SVG'yi gömülü raster olarak çizemez; yalnızca slotları çizer).
        'resvg_bin'  => env('CREATIVE_RESVG_BIN', 'resvg'),
        'timeout'    => (int) env('CREATIVE_RENDER_TIMEOUT', 120),
        'scripts'    => [
            'inspect' => base_path('Modules/Creative/python/inspect_template.py'),
            'render'  => base_path('Modules/Creative/python/render.py'),
            'apply'   => base_path('Modules/Creative/python/apply_slots.py'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipografi
    |--------------------------------------------------------------------------
    | Metin slotlarında kullanılacak TTF font yolları. Şablon kendi fontunu
    | (SVG path'i) taşımıyorsa bu fontlar devreye girer.
    */
    'fonts' => [
        'regular' => env('CREATIVE_FONT_REGULAR', base_path('Modules/Creative/python/fonts/DejaVuSans.ttf')),
        'bold'    => env('CREATIVE_FONT_BOLD', base_path('Modules/Creative/python/fonts/DejaVuSans-Bold.ttf')),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Sahne Pipeline (Gemini compose → fal.ai idm-vton try-on)
    |--------------------------------------------------------------------------
    | İki aşamalı opsiyonel pipeline: 1) Gemini ile model/sahne kurgusu,
    | 2) fal.ai idm-vton ile ürünü modele giydirme. Anahtar yoksa ilgili sürücü
    | 'mock'a düşer (anahtarsız dev/test için placeholder üretir).
    | Sürücü env'leri eski AiStudio modülünden devralındı.
    */
    'ai' => [
        // gemini | mock
        'compose_driver' => env('AI_STUDIO_COMPOSE_DRIVER', 'gemini'),
        // fal | mock
        'tryon_driver'   => env('AI_STUDIO_TRYON_DRIVER', 'fal'),
        // gemini | mock — caption/hashtag üretimi
        'caption_driver' => env('CREATIVE_CAPTION_DRIVER', 'gemini'),

        // AI çağrıları yavaş; pipeline genel zaman aşımı (saniye).
        'timeout' => (int) env('CREATIVE_AI_TIMEOUT', 240),

        // AI sahne çıktılarının saklandığı dizin (creative.disk üzerinde).
        'output_dir' => 'ai-scenes',

        'gemini' => [
            'api_key'   => env('GEMINI_API_KEY', ''),
            'base_url'  => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model'     => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
            // Caption gibi salt-metin üretiminde kullanılan model.
            'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-2.5-flash'),
            // Görsel üretiminde her ikisi de zorunlu; yalnız IMAGE → 400.
            'modalities' => ['TEXT', 'IMAGE'],
        ],

        'fal' => [
            'key'        => env('FAL_KEY', ''),
            'model'      => env('FAL_TRYON_MODEL', 'fal-ai/idm-vton'),
            'base_url'   => env('FAL_BASE_URL', 'https://queue.fal.run'),
            // Queue poll: deneme sayısı ve aralık (saniye).
            'poll_tries'    => (int) env('FAL_POLL_TRIES', 40),
            'poll_interval' => (int) env('FAL_POLL_INTERVAL', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marka (Brand Kit) varsayılanları
    |--------------------------------------------------------------------------
    | brand_kits tablosunda varsayılan kit yoksa veya bir token eksikse devreye
    | giren güvenli değerler. BrandTokenService bunları DB kit'iyle birleştirir.
    | Slot fill'i "token:primary" gibi yazılırsa render bu paletten çözer.
    */
    'brand' => [
        'defaults' => [
            'palette' => [
                'primary'    => '#111827',
                'secondary'  => '#6b7280',
                'accent'     => '#2563eb',
                'background' => '#ffffff',
                'text'       => '#111827',
            ],
            'spacing' => [
                'sm' => 8,
                'md' => 16,
                'lg' => 32,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Depolama
    |--------------------------------------------------------------------------
    */
    'disk'       => env('CREATIVE_DISK', 'public'),
    'output_dir' => 'creatives',
];
