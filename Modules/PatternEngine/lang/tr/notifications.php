<?php

// PatternEngine bildirimleri (zil menüsü). Kullanım: __('patternengine::notifications.anahtar').

return [

    'assignment' => [
        'assigner_fallback' => 'Bir koordinatör',
        'assignee_fallback' => 'Atanan kişi',
        'assigned_title' => 'Yeni iş atandı',
        'assigned' => ':assigner sana “:title” işini atadı.',
        'delivered_title' => 'İş teslim edildi',
        'delivered' => ':assignee “:title” işini teslim etti.',
        'accepted_title' => 'İşin kabul edildi',
        'accepted' => '“:title” işin kabul edildi.',
        'rejected_title' => 'İş geri gönderildi',
        'rejected' => '“:title” reddedildi.',
        'rejected_with_note' => '“:title” reddedildi: :note',
    ],

];
