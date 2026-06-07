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
    | Depolama
    |--------------------------------------------------------------------------
    */
    'disk'       => env('CREATIVE_DISK', 'public'),
    'output_dir' => 'creatives',
];
