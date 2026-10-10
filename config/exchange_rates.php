<?php

return [
    // TCMB günlük bülteni (bugün) ve geçmiş gün arşivi (yyyymm/ddmmyyyy.xml)
    'tcmb' => [
        'today_url'   => 'https://www.tcmb.gov.tr/kurlar/today.xml',
        'archive_url' => 'https://www.tcmb.gov.tr/kurlar/%s/%s.xml',
        'timeout'     => 15,
    ],

    // Çevrimde varsayılan kur tipi: döviz satış (dövizli alımda ödenecek TL karşılığı)
    'default_type' => 'forex_selling',

    // Ana sayfadaki kur göstergesinde sırayla gösterilenler
    'dashboard_currencies' => ['USD', 'EUR', 'GBP'],
];
