<?php

// Product modülünün kullanıcıya dönük uyarı, hata ve başarı mesajları.
// Kullanım: __('product::messages.anahtar'). Süper Admin › Dil Dosyaları'ndan düzenlenebilir.

return [

    // Başarı (flash)
    'image_uploaded' => 'Görsel yüklendi.',
    'price_list_saved' => 'Fiyat listesi güncellendi.',
    'price_updated' => 'Fiyat güncellendi.',
    'price_list_deleted' => 'Fiyat listesi silindi.',

    // Silme / işlem engelleri
    'brand_has_products' => 'Bu markaya bağlı ürünler var, önce ürünlerin markasını değiştirin.',
    'carrier_has_orders' => 'Bu kargo firmasına bağlı siparişler var. Silmek yerine pasife alın.',
    'warehouse_has_stock' => 'Bu depoda stok var, önce stokları başka depoya transfer edin.',
    'category_self_parent' => 'Bir kategori kendisinin üstü olamaz.',
    'stock_negative' => 'Stok negatife düşemez.',
    'sku_locked' => 'Ürün SKU\'su oluşturulduktan sonra değiştirilemez.',

    // Durum geçişleri
    'invalid_transition' => '":from" durumundan ":to" durumuna geçiş yapılamaz.',

    // Varyant silme
    'variant_in_use' => '":sku" varyantı silinemez: :reason.',
    'variant_in_use_reasons' => [
        'has_stock' => 'depoda stoku var — önce stok hareketiyle sıfırlayın',
        'has_movements' => 'stok hareket geçmişi var',
        'referenced' => 'başka bir kayıtta (ör. üretim emri) kullanılıyor',
    ],

    // Stok
    'insufficient_stock' => 'Varyant #:variant için yeterli stok yok (istenen: :requested, mevcut: :available).',
    'stock_notes' => [
        'order_deduction' => 'Sipariş kesimi',
        'order_cancel_return' => 'Sipariş iptali - stok iadesi',
    ],

    // Barkod
    'barcode_misconfigured' => 'Barkod ayarları hatalı: :reason',
    'barcode_reasons' => [
        'prefix_invalid' => 'ülke/firma kodu tanımlı değil veya sayısal değil.',
        'range_too_long' => 'seri aralığı önekle birlikte 12 haneye sığmıyor.',
    ],
    'barcode_range_exhausted' => 'Belirlenen barkod aralığında (:min-:max) boş seri numarası kalmadı.',

    // Hazırlık (readiness)
    'not_ready' => 'Ürün ":capability" için hazır değil: :missing',
    'readiness' => [
        'needs_image' => 'En az bir ürün görseli gerekli',
        'needs_marketplace_mapping' => 'Kategori için pazaryeri eşlemesi tanımlı değil',
    ],

    // AI açıklama üretimi
    'ai' => [
        'call_failed' => 'AI çağrısı başarısız: :error',
        'missing_fields' => 'AI cevabı beklenen alanları içermiyor (public_description / tenant_description).',
        'empty_response' => 'AI boş cevap döndürdü.',
        'invalid_json' => 'AI cevabı JSON olarak parse edilemedi.',
    ],

    // Form doğrulama
    'validation' => [
        'slug_format' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        'code_format' => 'Kod yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        'icon_format' => 'Geçersiz ikon: listeden bir ikon seçin.',
        'parent_cycle' => 'Kategori kendisinin ya da kendi alt kategorisinin altına taşınamaz.',
        'warehouse_code_format' => 'Depo kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        'attribute_key_format' => 'Anahtar küçük harfle başlamalı, yalnızca küçük harf/rakam/alt çizgi içerebilir.',
        'variants_required' => 'En az bir varyant eklenmelidir.',
        'variant_sku_distinct' => 'Varyant SKU\'ları benzersiz olmalıdır.',
        'color_hex_format' => 'Renk kodu #RRGGBB formatında olmalıdır.',
        'status_required' => 'Durum zorunludur.',
        'order_status_invalid' => 'Geçersiz sipariş durumu.',
        'product_status_invalid' => 'Geçersiz ürün durumu.',
    ],

];
