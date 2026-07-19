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
        // Varsayılan AÇIK: üretilen görsel her zaman yüksek çözünürlük/keskinlikle
        // kaydedilmeli (iş kararı). FSRCNN_x2.pb ağırlığı repoda mevcut; opencv
        // yoksa/model bulunamazsa sessizce Lanczos fallback'e düşer, hata fırlatmaz.
        'enabled'    => (bool) env('CREATIVE_ENHANCE_ENABLED', true),
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
    | Giydirme öncesi giysi görseli hazırlığı (EXIF + kırpma + boyut)
    |--------------------------------------------------------------------------
    | Ürün giydirme ekranından yüklenen ana/detay görselleri (telefon fotoğrafı
    | olabilir) AI'ya gitmeden önce Pillow ile temizlenir: EXIF döndürme, düz
    | arka plan kırpma, boyut sınırlama. Motor: python (Pillow) → yoksa
    | passthrough (NullGarmentPreparer). enhance.* ile aynı Process deseni.
    */
    'garment_prep' => [
        'enabled'     => (bool) env('CREATIVE_GARMENT_PREP_ENABLED', true),
        'driver'      => env('CREATIVE_GARMENT_PREP_DRIVER', 'python'), // python | null
        'python_bin'  => env('CREATIVE_PYTHON_BIN', 'python3'),
        'script'      => base_path('Modules/Creative/python/prepare_garment.py'),
        'max_side'    => (int) env('CREATIVE_GARMENT_PREP_MAX_SIDE', 2048),
        'trim_border' => (bool) env('CREATIVE_GARMENT_PREP_TRIM_BORDER', true),
        'timeout'     => (int) env('CREATIVE_GARMENT_PREP_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Giysi detay görseli otomatik etiketleme (yerel zero-shot CLIP)
    |--------------------------------------------------------------------------
    | Try-on ekranından yüklenen detay görsellerinin (yaka/düğme/kol ucu vb.)
    | neyi gösterdiğini yerel bir ML modeliyle (open_clip, Gemini/bulut çağrısı
    | OLMADAN) tahmin edip kullanıcının elle yazdığı etikete ÖNERİ olarak sunar.
    | Motor: python (open_clip) → yoksa passthrough (NullGarmentDetailClassifier).
    | garment_prep.* ile aynı Process deseni. torch/open_clip AĞIR bağımlılıklar
    | olduğundan varsayılan KAPALI — ops Python ortamını kurana kadar aranmaz.
    | python_bin diğer creative.* adımlarından (enhance/garment_prep/render)
    | KASITLI olarak ayrı bir env değişkeni kullanır: torch/open_clip sistem
    | Python'ına değil, bu özelliğe özel bir venv'e kurulması önerilir — böylece
    | diğer adımların (opencv, Pillow) Python ortamı hiç etkilenmez. Ayrı env
    | tanımlı değilse CREATIVE_PYTHON_BIN'e (dolayısıyla sistem python3'üne) düşer.
    */
    'detail_classification' => [
        'enabled'    => (bool) env('CREATIVE_DETAIL_CLASSIFICATION_ENABLED', false),
        'driver'     => env('CREATIVE_DETAIL_CLASSIFICATION_DRIVER', 'python'), // python | null
        'python_bin' => env('CREATIVE_DETAIL_CLASSIFICATION_PYTHON_BIN', env('CREATIVE_PYTHON_BIN', 'python3')),
        'script'     => base_path('Modules/Creative/python/classify_garment_detail.py'),
        'model_name' => env('CREATIVE_DETAIL_CLASSIFICATION_MODEL', 'ViT-B-32'),
        'pretrained' => env('CREATIVE_DETAIL_CLASSIFICATION_PRETRAINED', 'laion2b_s34b_b79k'),
        'top_k'      => (int) env('CREATIVE_DETAIL_CLASSIFICATION_TOP_K', 3),
        'threshold'  => (float) env('CREATIVE_DETAIL_CLASSIFICATION_THRESHOLD', 0.15),
        'timeout'    => (int) env('CREATIVE_DETAIL_CLASSIFICATION_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Giysi parça tespiti (torchvision, kendi verinizle fine-tune)
    |--------------------------------------------------------------------------
    | Yüklenen giysi görselindeki parçaların (yaka/cep/etek/kol ucu vb.)
    | konumunu (bbox) tespit eder — detail_classification'ın aksine bu bir
    | SINIFLANDIRMA değil NESNE TESPİTİ'dir. Tespit sonucu hem detay
    | sayfasında görselleştirilir (raporlama) hem de otomatik kırpılan parça
    | görselleri Gemini try-on'a ek referans olarak beslenir (bkz.
    | GarmentScanService::cropsForTryOn, GeminiTryOnPromptBuilder::describeExtras).
    |
    | Lisans kararı: Ultralytics YOLO iç kullanımda bile Enterprise lisans
    | gerektirir; DeepFashion2/Fashionpedia parça seviyesinde ticari kullanıma
    | uygun değildir. Bunun yerine torchvision (BSD, zaten kurulu) + kendi
    | ürün fotoğraflarınızla fine-tune edilen bir ağırlık dosyası kullanılır
    | (bkz. train_script). weights_path dosyası YOK sayılırsa (ilk fine-tune
    | çalıştırılmadan önce) sürücü otomatik olarak NullGarmentPartDetector'a
    | düşer — enabled=true olsa bile hiçbir Python çağrısı yapılmaz
    | (CreativeServiceProvider'daki is_file() kontrolü).
    */
    'garment_detection' => [
        'enabled'    => (bool) env('CREATIVE_GARMENT_DETECTION_ENABLED', false),
        'driver'     => env('CREATIVE_GARMENT_DETECTION_DRIVER', 'python'), // python | null
        'python_bin' => env('CREATIVE_GARMENT_DETECTION_PYTHON_BIN', env('CREATIVE_PYTHON_BIN', 'python3')),
        'script'       => base_path('Modules/Creative/python/detect_garment_parts.py'),
        'train_script' => base_path('Modules/Creative/python/train_garment_parts.py'),
        // Fine-tune edilmiş model ağırlığı — gitignore'lu, deploy/eğitim zamanında
        // üretilir (repoya commitlenmez). Dosya yoksa tespit sessizce devre dışı kalır.
        'weights_path' => base_path('Modules/Creative/python/models/garment_parts_latest.pt'),
        'min_confidence' => (float) env('CREATIVE_GARMENT_DETECTION_MIN_CONFIDENCE', 0.35),
        'max_detections' => (int) env('CREATIVE_GARMENT_DETECTION_MAX', 20),
        // Otomatik tespitten Gemini'ye ek referans olarak beslenecek en fazla parça sayısı.
        'max_auto_crops' => (int) env('CREATIVE_GARMENT_DETECTION_MAX_CROPS', 4),
        // Fine-tune eğitiminde bir etiketin dahil edilmesi için gereken asgari
        // manuel işaretlenmiş örnek sayısı (bkz. creative:train-garment-detector).
        'min_examples_per_label' => (int) env('CREATIVE_GARMENT_DETECTION_MIN_EXAMPLES', 40),
        'timeout'       => (int) env('CREATIVE_GARMENT_DETECTION_TIMEOUT', 30),
        'train_timeout' => (int) env('CREATIVE_GARMENT_DETECTION_TRAIN_TIMEOUT', 3600),

        // Zero-shot bootstrap dedektörü (OWLv2, Apache-2.0 — bkz. ROADMAP.md Faz G.3b):
        // fine-tune edilmiş ağırlık dosyası henüz yokken (weights_path is_file() false)
        // gerçek öneri kutuları üretir; kaynak her zaman detections[].source='zeroshot'
        // olarak damgalanır (GarmentScanService::normalizeAndNameLabels). Bu yüzden
        // cropsForTryOn (source='auto' filtreler) ve creative:train-garment-detector
        // (source='manual' filtreler) tarafından asla otomatik güvenilmez — yalnız
        // etiketleme aracında bir insan onaylayınca (source→manual) devreye girer.
        // Varsayılan KAPALI: transformers + ~1GB model ağırlığı ilk çalıştırmada iner
        // ve her generate() çağrısına birkaç saniyelik CPU çıkarım maliyeti ekler.
        'zero_shot' => [
            'enabled' => (bool) env('CREATIVE_GARMENT_ZERO_SHOT_ENABLED', false),
            'script'  => base_path('Modules/Creative/python/detect_garment_parts_zeroshot.py'),
            // HuggingFace model kimliği (transformers.pipeline). Apache-2.0.
            'model_name' => env('CREATIVE_GARMENT_ZERO_SHOT_MODEL', 'google/owlv2-base-patch16-ensemble'),
            // Fine-tune edilmiş modelden daha gürültülü skorlar üretir — ayrı, daha
            // düşük bir varsayılan; gerçek verilerle kalibrasyon gerekebilir.
            'min_confidence' => (float) env('CREATIVE_GARMENT_ZERO_SHOT_MIN_CONFIDENCE', 0.15),
            'timeout' => (int) env('CREATIVE_GARMENT_ZERO_SHOT_TIMEOUT', 60),
        ],

        // Bir etiket İLK KEZ oluşturulurken (GarmentScanService::resolveLabel/
        // normalizeAndNameLabels) verilecek sabit korunma sınıfı + taban öncelik.
        // Listede olmayan (yeni/serbest yazılan) etiketler DB kolon varsayılanına
        // düşer (appearance/medium) — bkz. migration
        // add_preservation_fields_to_creative_garment_labels_table.
        'label_defaults' => [
            'logo'          => ['category' => 'identity',     'priority' => 'critical'],
            'baski_desen'   => ['category' => 'identity',     'priority' => 'critical'],
            'nakis'         => ['category' => 'identity',     'priority' => 'critical'],
            'dugme'         => ['category' => 'hardware',      'priority' => 'critical'],
            'fermuar'       => ['category' => 'hardware',      'priority' => 'critical'],
            'citcit'        => ['category' => 'hardware',      'priority' => 'critical'],
            'percin'        => ['category' => 'hardware',      'priority' => 'critical'],
            'aksesuar_detay'=> ['category' => 'hardware',      'priority' => 'high'],
            'yaka'          => ['category' => 'appearance',    'priority' => 'high'],
            'kol_ucu'       => ['category' => 'appearance',    'priority' => 'high'],
            'cep'           => ['category' => 'appearance',    'priority' => 'high'],
            'etek'          => ['category' => 'appearance',    'priority' => 'high'],
            'kapusen'       => ['category' => 'appearance',    'priority' => 'high'],
            'kumas_dokusu'  => ['category' => 'appearance',    'priority' => 'medium'],
            'dikis'         => ['category' => 'construction',  'priority' => 'medium'],
            'firfir'        => ['category' => 'appearance',    'priority' => 'high'],
            'puf_kol'       => ['category' => 'appearance',    'priority' => 'high'],
            'arkadan'       => ['category' => 'appearance',    'priority' => 'low'],
            'yandan'        => ['category' => 'appearance',    'priority' => 'low'],
        ],

        /*
        |------------------------------------------------------------------------
        | Gemini Vision parça analizi (Garment Identity Preservation, Faz G.5)
        |------------------------------------------------------------------------
        | Her tespit edilen parçanın kırpılmış görseli için Gemini Vision'a AYRI
        | bir analiz çağrısı yapıp renk/desen/doku/kumaş/donanım gibi özellikleri
        | yapılandırılmış (enum + confidence) JSON olarak çıkarır — sonuç
        | creative_garment_scans.detections[].analysis'te VERSIONED bir zarfla
        | saklanır (analysis_version/model/prompt_version/generated_at/data).
        | Maliyet/gecikme nedeniyle varsayılan KAPALI (detail_classification ile
        | aynı opt-in mantığı) ve HER CROP İÇİN BİR KERE çalışır (scan zaten
        | image_hash ile dedup ediyor) — bir garment ~20 poza yeniden
        | kullanıldığı için per-generate-call değil per-unique-crop maliyet.
        |
        | confidence_threshold: bu değerin altındaki alanlar try-on prompt'una
        | HİÇ girmez (GarmentIdentityRuleEngine filtreler) — LLM'in emin olmadığı
        | bir tahmin asla "gerçek" diye modele verilmez.
        | enums: Gemini'nin serbest metin yerine kapalı bir sözlükten seçmesi
        | için — "Dark Blue"/"Navy"/"Midnight" gibi tutarsız serbest metinleri
        | önler. Eşleşen yoksa değer OTHER olur, ne gördüğü raw_text'e yazılır
        | (veri kaybı olmaz, ama prompt/rapor mantığı sadece enum'a güvenir).
        */
        'analysis' => [
            'enabled' => (bool) env('CREATIVE_GARMENT_ANALYSIS_ENABLED', false),
            'driver'  => env('CREATIVE_GARMENT_ANALYSIS_DRIVER', 'gemini'), // gemini | mock
            'confidence_threshold' => (float) env('CREATIVE_GARMENT_ANALYSIS_CONFIDENCE_THRESHOLD', 0.6),
            // Crop bu kenar boyutundan küçükse (ör. küçük bir düğme kırpıntısı)
            // analiz/kayıt öncesi Lanczos ile bu asgari değere yükseltilir —
            // Gemini'nin küçük donanım detaylarını netçe okuyabilmesi için.
            'min_crop_side_px' => (int) env('CREATIVE_GARMENT_ANALYSIS_MIN_CROP_PX', 768),
            // Prompt metni değişince (enum listesi, talimat cümlesi vb.) ELLE
            // artırılır — analysis.prompt_version eski kayıtlarla karşılaştırılıp
            // "stale" (yeniden analiz gereken) tespitler bulunabilir.
            'prompt_version' => 1,
            // Bütünsel "bu ürünü ayırt eden en fazla 5 detay" çağrısı (madde 10) —
            // parça analizinden AYRI, ek bir Gemini çağrısı; garment başına 1 kez.
            'identity_summary_enabled' => (bool) env('CREATIVE_GARMENT_IDENTITY_SUMMARY_ENABLED', false),
            'enums' => [
                'color' => [
                    'BLACK', 'WHITE', 'GRAY', 'NAVY_BLUE', 'BLUE', 'LIGHT_BLUE', 'RED', 'BURGUNDY',
                    'PINK', 'ORANGE', 'YELLOW', 'GREEN', 'OLIVE', 'BROWN', 'BEIGE', 'CREAM', 'PURPLE', 'MULTICOLOR', 'OTHER',
                ],
                'pattern' => [
                    'SOLID', 'STRIPED', 'PLAID', 'FLORAL', 'POLKA_DOT', 'GRAPHIC_PRINT',
                    'GEOMETRIC', 'ANIMAL_PRINT', 'CAMOUFLAGE', 'TEXTURED_KNIT', 'OTHER',
                ],
                'texture' => [
                    'SMOOTH', 'RIBBED', 'KNIT', 'WOVEN', 'QUILTED', 'FLEECE', 'CORDUROY', 'MESH', 'LACE', 'OTHER',
                ],
                'fabric' => [
                    'COTTON', 'WOOL', 'POLYESTER', 'LINEN', 'DENIM', 'LEATHER', 'SILK',
                    'VISCOSE', 'NYLON', 'CASHMERE', 'VELVET', 'OTHER',
                ],
                'stitching' => [
                    'SINGLE_NEEDLE', 'DOUBLE_NEEDLE', 'OVERLOCK', 'TOPSTITCH', 'BLIND_HEM', 'NONE_VISIBLE', 'OTHER',
                ],
                'hardware_type' => [
                    'PLASTIC_BUTTON', 'METAL_BUTTON', 'PEARL_BUTTON', 'FABRIC_COVERED_BUTTON',
                    'ZIPPER_METAL', 'ZIPPER_PLASTIC', 'SNAP', 'RIVET', 'BUCKLE', 'DRAWSTRING', 'NONE', 'OTHER',
                ],
                'priority' => ['critical', 'high', 'medium', 'low'],
            ],
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
    | AI Sahne Pipeline (Gemini compose → Gemini try-on)
    |--------------------------------------------------------------------------
    | İki aşamalı opsiyonel pipeline: 1) Gemini ile model/sahne kurgusu,
    | 2) Gemini Nano Banana 2 ile ürünü modele giydirme. Anahtar yoksa ilgili
    | sürücü 'mock'a düşer (anahtarsız dev/test için placeholder üretir).
    | Sürücü env'leri eski AiStudio modülünden devralındı.
    |
    | fal.ai fashn/tryon v1.6 karşılaştırmalı test edildi: gerçek A/B testinde
    | iki parçalı (ör. ceket+pantolon) ürünleri tek parça gibi yanlış render
    | etti (bkz. proje geçmişi/2026-07-15 testleri), Gemini ise doğru giydirdi.
    | fal sürücüsü koddan kaldırılmadı — env ile hâlâ seçilebilir, tek parça
    | ürünlerde tekrar denenebilir — ama artık varsayılan değil.
    */
    'ai' => [
        // gemini | mock
        'compose_driver' => env('AI_STUDIO_COMPOSE_DRIVER', 'gemini'),
        // gemini | fal | mock — ürün giydirme (try-on). Varsayılan: Gemini Nano Banana 2
        // (iki parçalı ürünlerde fal/fashn'e göre belirgin şekilde daha tutarlı sonuç verdi).
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
            'model'      => env('FAL_TRYON_MODEL', 'fal-ai/fashn/tryon/v1.6'),
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
        |
        | Her parça TEK bir sabit metin yerine bir VARYANT LİSTESİDİR — üretim
        | anında MannequinPromptBuilder her parçadan rastgele bir varyant seçer.
        | Amaç: aynı profilden üretilen tüm mankenlerin "kardeş" gibi birebir
        | aynı yüze sahip olmasını önlemek, aynı zamanda etnik/ten çapasını
        | korumak (varyantlar hep aynı "Anadolu/buğday" ailesinde kalır).
        */
        'prompt' => [
            'default_identity_profile' => env('CREATIVE_IDENTITY_PROFILE', 'turkish_anatolian'),

            'identity_profiles' => [
                'turkish_anatolian' => [
                    'face_structure' => [
                        'a softly oval face with naturally rounded friendly cheeks and distinct, authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'a heart-shaped face with a gently pointed chin, defined cheekbones and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'an angular face with a strong, well-defined jawline, straight brows and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'a rounded face with soft full cheeks, a gentle jawline and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'an oval face with high cheekbones, a straight nose bridge and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'a square-ish face with a defined jaw, softly arched brows and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'a long, narrow face with a high forehead and subtly hollowed cheeks and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                        'a diamond-shaped face with wide cheekbones tapering to a narrow chin and authentic Anatolian Turkish (Alp-Mediterranean) facial characteristics',
                    ],
                    'nose' => [
                        'a straight, refined nose bridge with a slightly narrow tip',
                        'a subtly aquiline (softly hooked) nose bridge typical of the region',
                        'a small, softly rounded button nose',
                        'a straight nose with a slightly wider, natural base',
                        'a delicate, slightly upturned nose tip',
                    ],
                    'skin' => [
                        'a realistic, warm-wheat "buğday" complexion with natural sun-kissed undertones, strictly avoiding any overly dark, flat olive or orange tones',
                        'a realistic, warm light-olive complexion with soft golden undertones, strictly avoiding any overly dark, flat olive or orange tones',
                        'a realistic, warm honey-tan complexion with natural sun-kissed undertones, strictly avoiding any overly dark, flat olive or orange tones',
                        'a realistic, warm ivory-beige complexion with subtle rosy undertones, strictly avoiding any overly dark, flat olive or orange tones',
                    ],
                    'eyes' => [
                        'expressive, medium-sized, dark brown, almond-shaped ("badem göz") eyes',
                        'large, deep hazel-brown, almond-shaped eyes with thick natural lashes',
                        'expressive, warm amber-brown, slightly upturned almond eyes',
                        'deep-set, dark brown, round-almond eyes with a soft gaze',
                        'striking, medium-sized, chestnut-brown almond eyes under naturally arched brows',
                        'wide-set, dark hazel eyes with a bright, alert gaze',
                        'close-set, deep brown eyes with a subtle downturn giving a gentle expression',
                    ],
                    'hair' => [
                        'natural dark brown hair with a soft wavy texture and realistic individual loose strands catching the light',
                        'natural black hair with a sleek straight texture and realistic individual loose strands catching the light',
                        'natural chestnut-brown hair with loose soft curls and realistic individual loose strands catching the light',
                        'natural dark brown hair with a light tousled texture and realistic individual loose strands catching the light',
                        'natural deep espresso-brown hair with a subtle wave and realistic individual loose strands catching the light',
                    ],
                    'hairstyle' => [
                        'worn long, past the shoulders, with a center part',
                        'worn in a sleek, low ponytail with a side part',
                        'shoulder-length with a deep side part and soft face-framing layers',
                        'gathered in a loose, low bun with a few strands left loose around the face',
                        'long and worn in a single loose braid over one shoulder',
                        'worn long with a blunt fringe (bangs) across the forehead',
                    ],
                    'distinguishing_feature' => [
                        'clear, even-toned skin with no notable marks',
                        'a small natural beauty mark just above the left corner of the mouth',
                        'a faint scattering of light freckles across the nose bridge and upper cheeks',
                        'a subtle dimple on the right cheek visible when smiling',
                        'a naturally slight gap between the front two teeth',
                        'a small beauty mark near the outer corner of the right eye',
                    ],
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

            // Poz başına rastgele seçilen yüz ifadesi varyantı — MannequinPosePromptBuilder
            // kullanır. Amaç: aynı mankenin farklı pozlarda/ürünlerde HEP AYNI donmuş
            // ifadeyle çıkması (belirgin bir "AI" işareti) yerine gerçek bir çoklu-kare
            // stüdyo çekiminde olduğu gibi ifadenin çekimden çekime doğal biçimde
            // değişmesi — yüz KİMLİĞİ (yapı/ten/saç) hiçbir varyantta değişmez, sadece
            // anlık ifade. Tüm yaş gruplarında uygun kalması için nötr/sıcak tutulur.
            'pose_expressions' => [
                'a soft, natural closed-mouth smile with relaxed, gently lowered eyelids',
                'a warm, genuine smile that softly crinkles the skin around the eyes',
                'a calm, composed expression with a relaxed, confident gaze directly at the camera',
                'a gentle, relaxed half-smile, approachable and at ease',
                'an alert, natural expression looking slightly off to the side, as if caught mid-moment',
                'a soft, serene expression with a barely-there smile and relaxed brows',
                'a cheerful, engaged expression with bright, naturally focused eyes',
                'a relaxed, friendly expression with softly parted lips, mid-conversation feel',
                'a quiet, thoughtful expression with a subtle, closed-mouth smile',
                'a bright, confident expression with a light, natural laugh-adjacent smile',
            ],
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
    | ardından ürünler bu pozlara fashn/tryon ile giydirilip product_images'a yazılır.
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
