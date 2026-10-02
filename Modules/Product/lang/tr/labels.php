<?php

// Product modülünün ekranda görünen etiketleri (durumlar, yetenekler, ürün özellikleri).
// Kullanım: __('product::labels.anahtar').

return [

    'product_status' => [
        'draft' => 'Taslak',
        'published' => 'Yayında',
        'archived' => 'Arşivlendi',
        'development' => 'Geçici Kart',
    ],

    'order_status' => [
        'pending' => 'Beklemede',
        'confirmed' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'shipped' => 'Kargolandı',
        'delivered' => 'Teslim Edildi',
        'cancelled' => 'İptal Edildi',
    ],

    'return_status' => [
        'requested' => 'Karar Bekliyor',
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
        'refunded' => 'İade Edildi',
        'cancelled' => 'İptal Edildi',
    ],

    'capability' => [
        'try_on' => 'Sanal Giydirme',
        'creative_render' => 'Kreatif Görsel Üretimi',
        'marketplace_push' => 'Pazaryeri Gönderimi',
    ],

    // Ürün detay sayfası "öne çıkan özellikler" listesi
    'features' => [
        'free_shipping' => 'Ücretsiz kargo',
        'new_season' => 'Yeni sezon ürünü',
        'domestic' => 'Yerli üretim',
        'fabric' => ':composition kumaş',
        'min_order' => 'Minimum :qty adet sipariş',
    ],

    // Ürün detay sayfası "Özellikler" tablosu satır başlıkları
    'specs' => [
        'brand' => 'Marka',
        'category' => 'Kategori',
        'gender' => 'Cinsiyet',
        'sku' => 'SKU',
        'barcode' => 'Barkod',
        'material' => 'Materyal',
        'composition' => 'Kumaş Bileşimi',
        'origin' => 'Menşei',
        'weight' => 'Ağırlık',
        'desi' => 'Desi',
        'sizes' => 'Bedenler',
        'colors' => 'Renkler',
        'care' => 'Bakım',
        'shipping_time' => 'Kargo Süresi',
    ],

];
