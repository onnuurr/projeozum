<?php

// Creative modülünün ekranda görünen etiketleri. Kullanım: __('creative::labels.anahtar').

return [

    // Bildirim ve sohbetlerde ürün adı yoksa
    'product_fallback' => 'Ürün',

    // İncelenen görsel türleri
    'subject_type' => [
        'mannequin' => 'Manken',
        'asset' => 'Sosyal medya tasarımı',
        'tryon' => 'Giydirme görseli',
    ],

    // Ret analizi uyarılarındaki konu ve metrik adları
    'review_subjects' => [
        'tryon_results' => 'Giydirme',
        'mannequins' => 'Manken',
        'creative_assets' => 'Creative Studio',
        'detection_summary' => 'Giysi parça tespiti',
        'color_audit_summary' => 'Renk sadakati',
    ],
    'review_metrics' => [
        'rejection_rate' => 'Genel ret oranı',
        'zero_detection_rate' => 'Sıfır tespit oranı',
        'color_over_warn_rate' => 'Delta E eşik üstü oranı',
        'tag' => 'Ret etiketi: :tag',
    ],

    // Marka kitinden üretilen şablonlara konan yer tutucu metinler
    'template_placeholders' => [
        'headline' => 'Başlık Buraya',
        'sub_headline' => 'Alt başlık metni',
        'cta_button' => 'Şimdi Keşfet',
    ],

];
