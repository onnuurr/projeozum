<?php

// Creative modülü bildirimleri (zil menüsü). Kullanım: __('creative::notifications.anahtar').

return [

    'pending_review' => [
        'title' => 'Onay bekleyen görsel',
        'mannequin' => '":label" mankeni onayınızı bekliyor.',
        'asset' => '":label" için üretilen sosyal medya tasarımı onayınızı bekliyor.',
        'tryon' => '":label" için üretilen giydirme görseli onayınızı bekliyor.',
    ],

    'review_decision' => [
        'approved_title' => 'Görsel onaylandı',
        'approved' => ':type ":label" onaylandı.',
        'rejected_title' => 'Görsel reddedildi',
        'rejected' => ':type ":label" reddedildi.',
        'rejected_with_note' => ':type ":label" reddedildi: :note',
    ],

    'review_report' => [
        'title' => 'Haftalık giydirme ret raporu hazır',
        'empty' => 'Bu hafta incelenen görsel yok.',
        'summary' => ':rejected/:total görsel reddedildi.',
        'top_tag' => ' En sık neden: ":tag" (:count).',
    ],

    'review_alert' => [
        'title' => 'Ret analizinde eşik aşıldı',
        'summary' => ':subject → :label: %:rate, :streak gündür eşik (%:threshold) üstünde.',
        'more' => ' (+:count uyarı daha)',
    ],

];
