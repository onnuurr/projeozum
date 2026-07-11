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
    | Görsel iyileştirme (üretim sonrası upscale + son dokunuş)
    |--------------------------------------------------------------------------
    | AI çıktısı görselin çözünürlüğünü/keskinliğini artırır. Motor: OpenCV
    | dnn_superres (model varsa) → yoksa Pillow LANCZOS fallback; ardından Pillow
    | ile unsharp/kontrast/doygunluk + sRGB. Devre dışıysa (veya driver=null)
    | enhancer passthrough (NullImageEnhancer) çalışır; opencv aranmaz.
    | UYARI: Upscaling yapısal AI hatalarını düzeltmez, keskinleştirir — bu yüzden
    | varsayılanlar muhafazakârdır (x2, ölçülü unsharp).
    */
    'enhance' => [
        'enabled'    => (bool) env('CREATIVE_ENHANCE_ENABLED', false),
        'driver'     => env('CREATIVE_ENHANCE_DRIVER', 'python'), // python | null
        'python_bin' => env('CREATIVE_PYTHON_BIN', 'python3'),
        'script'     => base_path('Modules/Creative/python/enhance.py'),
        'model_dir'  => base_path('Modules/Creative/python/models'),
        'model_name' => env('CREATIVE_ENHANCE_MODEL', 'fsrcnn'), // fsrcnn | edsr | lapsrn | espcn
        'scale'      => (int) env('CREATIVE_ENHANCE_SCALE', 2),
        'max_side'   => (int) env('CREATIVE_ENHANCE_MAX_SIDE', 2048),
        'unsharp'    => [
            'radius'    => (float) env('CREATIVE_ENHANCE_UNSHARP_RADIUS', 2.0),
            'percent'   => (int) env('CREATIVE_ENHANCE_UNSHARP_PERCENT', 120),
            'threshold' => (int) env('CREATIVE_ENHANCE_UNSHARP_THRESHOLD', 3),
        ],
        'contrast'   => (float) env('CREATIVE_ENHANCE_CONTRAST', 1.04),
        'saturation' => (float) env('CREATIVE_ENHANCE_SATURATION', 1.03),
        'timeout'    => (int) env('CREATIVE_ENHANCE_TIMEOUT', 120),
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
        // gemini | fal | mock — ürün giydirme (try-on). Varsayılan: Gemini Nano Banana 2.
        'tryon_driver'   => env('AI_STUDIO_TRYON_DRIVER', 'gemini'),
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
            // Try-on (giydirme) için kullanılan görsel modeli — Nano Banana 2.
            // Erişime göre '-preview' eki gerekebilir (gemini-3.1-flash-image-preview).
            'tryon_model' => env('GEMINI_TRYON_MODEL', 'gemini-3.1-flash-image'),
            // Manken KİMLİK görseli için ayrı model. Yüzün doğduğu aşama olduğu için
            // burada daha güçlü modele geçmek (try-on ile aynı 3.1) yüz/vücut
            // gerçekçiliğini artırır. Varsayılan davranış değişmesin diye 'model'e
            // (2.5) düşer; GEMINI_MANNEQUIN_MODEL ile 3.1'e yükseltilebilir.
            'mannequin_model' => env('GEMINI_MANNEQUIN_MODEL', env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image')),
            // Caption gibi salt-metin üretiminde kullanılan model.
            'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-2.5-flash'),
            // Görsel üretiminde her ikisi de zorunlu; yalnız IMAGE → 400.
            'modalities' => ['TEXT', 'IMAGE'],

            // Kimlik (manken) üretiminde generationConfig.imageConfig alanları.
            // aspectRatio tam boy kadraj için kritik (kare çıktı bacağı kırpar,
            // yüze piksel bırakmaz); imageConfig alan adları güncel API'ye göre
            // doğrulanmalı. Boş bırakılırsa GÖNDERİLMEZ (davranış değişmez) —
            // bir model alanı reddederse ilgili env'i boşaltmak yeter.
            'image' => [
                // Tam boy portre için 3:4 (veya 9:16). Boş → gönderilmez.
                'aspect_ratio' => env('GEMINI_IMAGE_ASPECT_RATIO', '3:4'),
                // 3.x modellerde "1K" | "2K" | "4K". 2.5'te desteklenmeyebilir;
                // bu yüzden varsayılan boş (env ile açılır).
                'size'         => env('GEMINI_IMAGE_SIZE', ''),
            ],
        ],

        'fal' => [
            'key'        => env('FAL_KEY', ''),
            'model'      => env('FAL_TRYON_MODEL', 'fal-ai/idm-vton'),
            'base_url'   => env('FAL_BASE_URL', 'https://queue.fal.run'),
            // Queue poll: deneme sayısı ve aralık (saniye).
            'poll_tries'    => (int) env('FAL_POLL_TRIES', 40),
            'poll_interval' => (int) env('FAL_POLL_INTERVAL', 3),
        ],

        /*
        |----------------------------------------------------------------------
        | Prompt mimarisi (kimlik duvarı + gerçekçilik/kamera)
        |----------------------------------------------------------------------
        | Manken kimlik ve try-on prompt'larının SABİT bölümleri. Amaç: yapay
        | zeka "cilasını" (plastik/3D görünüm) kırmak ve etnik kaymayı önleyip
        | tutarlı bir kimlik çapası vermek. Yaş/cinsiyet/poz/ürün DİNAMİK kalır.
        |
        | identity_profiles: yapısal (parça bazlı) çapa. Varsayılan Türk
        | (Anadolu/buğday) profili; bir manken alanı (skin_tone/hair/face)
        | verilirse o parça kullanıcı değeriyle EZİLİR (çift-talimat önlenir).
        */
        'prompt' => [
            'default_identity_profile' => env('CREATIVE_IDENTITY_PROFILE', 'turkish_anatolian'),

            'identity_profiles' => [
                'turkish_anatolian' => [
                    'face_structure' => 'a softly oval face with naturally rounded friendly cheeks and distinct, authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                    'skin'           => 'a realistic, warm-wheat "buğday" complexion with natural sun-kissed undertones, strictly avoiding any overly dark, flat olive or orange tones',
                    'eyes'           => 'expressive, medium-sized, dark brown, almond-shaped ("badem göz") eyes',
                    'hair'           => 'natural dark brown hair with a soft wavy texture and realistic individual loose strands catching the light',
                ],
                // Çapasız: yalnız kullanıcı alanları belirleyicidir.
                'none' => [],
            ],

            // Difüzyon-tarzı (Flux/MJ/SD3) "photorealistic" kelime yasağı.
            // Gemini bu kelimeye farklı tepki verdiği için varsayılan KAPALI;
            // CREATIVE_BAN_PHOTOREALISTIC=true ile A/B test edilebilir.
            'ban_photorealistic_wording' => (bool) env('CREATIVE_BAN_PHOTOREALISTIC', false),

            // Sabit ışık/kamera metadata dili (raw studio + Hasselblad).
            'camera_directive' => env(
                'CREATIVE_CAMERA_DIRECTIVE',
                'Shot on a medium format Hasselblad H6D camera with an 85mm lens at f/4.0, '
                . 'lit by soft, directional natural window daylight from the side, on a neutral, '
                . 'warm-toned minimalist photo studio background with a shallow depth of field and '
                . 'a subtle, organic film grain.',
            ),
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
    'disk'       => env('CREATIVE_DISK', env('MEDIA_DISK', 'public')),
    'output_dir' => 'creatives',

    /*
    |--------------------------------------------------------------------------
    | Sosyal medya çıktı formatları
    |--------------------------------------------------------------------------
    | Üretimde seçilen format çıktının nihai boyutunu belirler. Render, şablonu
    | kendi boyutunda kurguladıktan sonra çıktıyı bu ölçüye "cover" ile uydurur.
    | aspect: AI sahne prompt'una verilen oran ipucu.
    */
    'default_format' => 'instagram_story',
    'formats' => [
        'instagram_post'     => ['label' => 'Instagram Gönderi (Kare)', 'width' => 1080, 'height' => 1080, 'aspect' => '1:1 square'],
        'instagram_portrait' => ['label' => 'Instagram Dikey (4:5)',     'width' => 1080, 'height' => 1350, 'aspect' => '4:5 portrait'],
        'instagram_story'    => ['label' => 'Instagram / Story (9:16)',  'width' => 1080, 'height' => 1920, 'aspect' => '9:16 vertical'],
        'facebook_post'      => ['label' => 'Facebook Gönderi',          'width' => 1200, 'height' => 630,  'aspect' => '1.91:1 landscape'],
        'x_post'             => ['label' => 'X (Twitter) Gönderi',       'width' => 1600, 'height' => 900,  'aspect' => '16:9 landscape'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanal Manken Stüdyosu
    |--------------------------------------------------------------------------
    | AI ile yeniden kullanılabilir manken üretilir (compose driver), her mankene
    | aşağıdaki poz kataloğundan görseller üretilir (kimlik referans görseliyle),
    | ardından ürünler bu pozlara idm-vton ile giydirilip product_images'a yazılır.
    | Çıktı dizinleri creative.disk üzerinde tutulur.
    */
    'mannequin' => [
        // Referans manken + poz görsellerinin saklandığı kök dizin.
        'output_dir' => 'mannequins',
        // Bir mankenin "hazır" sayılması için gereken asgari hazır poz sayısı.
        'min_poses'  => 20,

        // Poz kataloğu (20+). Her poz Gemini'ye duruş yönergesi olarak verilir;
        // manken kimliği reference_image_path ile korunmaya çalışılır.
        'poses' => [
            ['key' => 'standing_front',       'label' => 'Ayakta cepheden',        'prompt' => 'standing upright facing the camera, relaxed shoulders, arms naturally at the sides'],
            ['key' => 'standing_three_qtr',   'label' => 'Üç-çeyrek duruş',        'prompt' => 'a relaxed three-quarter standing pose, weight on one leg and shoulders angled slightly to camera'],
            ['key' => 'standing_profile',     'label' => 'Profilden',              'prompt' => 'standing in full side profile, chin level, posture tall and elegant'],
            ['key' => 'standing_back',        'label' => 'Arkadan',                'prompt' => 'standing with the back to the camera, head turned slightly over the shoulder'],
            ['key' => 'contrapposto',         'label' => 'Kontraposto',            'prompt' => 'a contrapposto stance, hips shifted, one knee slightly bent, fashion-editorial feel'],
            ['key' => 'hand_on_hip',          'label' => 'El belde',               'prompt' => 'a confident frontal stance with one hand resting on the hip, chin level'],
            ['key' => 'both_hands_hips',      'label' => 'İki el belde',           'prompt' => 'standing with both hands on the hips, strong confident posture'],
            ['key' => 'arms_crossed',         'label' => 'Kollar kavuşmuş',        'prompt' => 'standing with arms gently crossed, calm and approachable expression'],
            ['key' => 'walking_stride',       'label' => 'Yürüyüş',                'prompt' => 'a dynamic walking pose mid-stride that conveys movement while staying in sharp focus'],
            ['key' => 'walking_runway',       'label' => 'Podyum yürüyüşü',        'prompt' => 'a runway walking pose, one foot crossing in front of the other, poised and deliberate'],
            ['key' => 'looking_over_shoulder','label' => 'Omuz üstü bakış',        'prompt' => 'body angled away while looking back over the shoulder toward the camera'],
            ['key' => 'hands_in_pockets',     'label' => 'Eller cepte',            'prompt' => 'standing casually with hands in pockets, easy relaxed mood'],
            ['key' => 'leaning_wall',         'label' => 'Duvara yaslı',           'prompt' => 'leaning against a plain wall with crossed ankles, casual editorial vibe'],
            ['key' => 'seated_chair',         'label' => 'Sandalyede oturma',      'prompt' => 'seated upright on a simple chair, hands resting on the lap, composed posture'],
            ['key' => 'seated_editorial',     'label' => 'Editorial oturma',       'prompt' => 'a seated editorial pose with an elongated silhouette presented to the camera'],
            ['key' => 'seated_floor',         'label' => 'Yerde oturma',           'prompt' => 'sitting on the floor with relaxed legs, natural candid posture'],
            ['key' => 'crouching',            'label' => 'Çömelme',                'prompt' => 'a low crouching pose, forearms on knees, urban editorial feel'],
            ['key' => 'arms_raised',          'label' => 'Kollar yukarıda',        'prompt' => 'a dynamic pose with both arms raised, energetic and expressive'],
            ['key' => 'adjusting_collar',     'label' => 'Yakaya dokunuş',         'prompt' => 'one hand lightly adjusting the collar or neckline, gaze toward camera'],
            ['key' => 'hand_in_hair',         'label' => 'Saça dokunuş',           'prompt' => 'one hand running through the hair, relaxed natural expression'],
            ['key' => 'twisting_torso',       'label' => 'Gövde dönüşü',           'prompt' => 'torso twisting toward the camera while hips face away, showing garment movement'],
            ['key' => 'three_qtr_back',       'label' => 'Üç-çeyrek arka',         'prompt' => 'a three-quarter back view, weight on one leg, head turned to camera'],
            ['key' => 'step_forward',         'label' => 'Öne adım',               'prompt' => 'taking a confident step forward toward the camera, arms swinging naturally'],
            ['key' => 'relaxed_lean',         'label' => 'Rahat yaslanış',         'prompt' => 'relaxed standing lean with weight back, hands loosely clasped in front'],
        ],
    ],
];
