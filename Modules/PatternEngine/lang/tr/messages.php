<?php

// PatternEngine (Atölye: kalıp kütüphanesi, dönüştürücü, konsept stüdyosu, atamalar)
// kullanıcıya dönük uyarı, hata ve başarı mesajları.
// Kullanım: __('patternengine::messages.anahtar'). Süper Admin › Dil Dosyaları'ndan düzenlenebilir.

return [

    // Kuyruk işleri (ekranda hata olarak görünür)
    'job_attempt_failed' => 'Deneme :attempt/:tries başarısız: :error',

    // Kalıp kütüphanesi
    'pattern' => [
        'pdf_queued_part' => ':count PDF incelemeye alındı',
        'skipped_part' => 'atlandı (boş/geçersiz): :files',
        'no_pdf_processed' => 'Hiçbir PDF işlenemedi. :details',
        'photos_queued' => ':count fotoğraf incelemeye alındı.',
        'import_failed' => 'İçe aktarılamadı: :error',
        'imported_from_link' => 'Linkten kalıp içe aktarıldı, inceleniyor.',
        'only_failed_retry' => 'Yalnızca çıkarımı başarısız taslaklar yeniden denenebilir.',
        'extraction_restarted' => 'Çıkarım yeniden başlatıldı.',
        'page_render_failed' => 'Sayfa render edilemedi: :error',
        'dxf_failed' => 'DXF üretilemedi: :error',
        'digitized' => 'Kalıp sayısallaştırıldı.',
        'created' => 'Kalıp eklendi.',
        'updated' => 'Kalıp güncellendi.',
        'deleted' => 'Kalıp silindi.',
        'vector_extraction_failed' => 'Vektör kalıp çıkarılamadı (taranmış/desteklenmeyen format olabilir).',
        'pdf_missing_on_disk' => 'Kalıp PDF dosyası diskte bulunamadı: :path',
        'photo_missing_on_disk' => 'Kalıp fotoğrafı diskte bulunamadı: :path',
    ],

    // Linkten içe aktarma
    'url_import' => [
        'host_not_allowed' => 'Bu adresten içe aktarım desteklenmiyor. İzinli kaynaklar: :hosts.',
        'no_pdf_on_page' => 'Sayfada PDF eki bulunamadı.',
        'not_a_pdf' => 'İndirilen dosya geçerli bir PDF değil.',
        'pdf_empty' => 'İndirilen PDF boş veya okunamıyor (taranmış/geçersiz olabilir).',
        'vk_unresolved' => 'VK belgesi çözülemedi: :error',
        'vk_unknown_error' => 'bilinmeyen hata',
        'vk_unreadable' => 'VK belgesi indirildi ama okunamadı.',
        'vk_too_large' => 'VK belgesi izin verilen boyutu aşıyor.',
        'download_failed' => 'İçerik indirilemedi (HTTP :status): :url',
        'too_large' => 'İndirilen dosya izin verilen boyutu aşıyor: :url',
        'source_not_allowed' => 'İzinli olmayan kaynak: :url',
    ],

    // PDF → DXF dönüştürücü
    'conversion' => [
        'queued' => ':count PDF dönüştürme kuyruğuna alındı.',
        'only_review_approvable' => 'Yalnızca incelemedeki işler onaylanabilir.',
        'no_dxf_cannot_approve' => 'DXF üretilemediği için onaylanamaz; reddedin veya yeniden işleyin.',
        'added_to_library' => 'Kalıp kütüphaneye eklendi.',
        'rejected' => 'İş reddedildi.',
        'requeued' => 'İş yeniden kuyruğa alındı.',
        'source_missing_on_disk' => 'Kaynak PDF diskte bulunamadı: :path',
    ],

    // Konsept stüdyosu
    'concept' => [
        'started' => 'Konsept üretimi başlatıldı.',
        'only_failed_retry' => 'Yalnızca üretimi başarısız konseptler yeniden denenebilir.',
        'restarted' => 'Üretim yeniden başlatıldı.',
        'mapped' => 'Konsept kalıba eşlendi.',
        'archived' => 'Konsept arşivlendi.',
        'flow_x_needs_pattern' => 'Akış X (önce kalıp) için geçerli bir kalıp seçilmelidir.',
    ],

    // Atamalar
    'assignment' => [
        'assigned' => 'İş atandı.',
        'updated' => 'Atama güncellendi.',
        'needs_subject' => 'Atama için en az bir tasarım kartı veya kalıp seçilmelidir.',
        'invalid_status' => 'Bu işlem \':status\' durumundaki bir atamada yapılamaz.',
    ],

    // Form doğrulama
    'validation' => [
        'garment_required' => 'Giysi tipi zorunludur.',
        'size_required' => 'Beden zorunludur.',
        'grade_size_different' => 'Ek bedenler taban bedenle aynı olamaz.',
        'fit_invalid' => 'Uyum yalnızca slim, regular veya loose olabilir.',
        'profile_name_required' => 'Profil adı zorunludur.',
        'profile_name_format' => 'Profil adı yalnızca küçük harf, rakam, "_" ve "-" içerebilir (ilk karakter harf/rakam).',
        'profile_name_reserved' => '\'ts_default\' referans profildir, bu adla kaydedilemez.',
        'sizes_required' => 'En az bir beden girilmelidir.',
        'provenance_source_required' => 'Ölçü tablosunun kaynağı zorunludur.',
        'image_required' => 'Bir teknik çizim/görsel seçilmelidir.',
        'image_type' => 'Yalnızca görsel dosyaları kabul edilir.',
        'image_mimes' => 'Görsel PNG veya JPEG formatında olmalıdır.',
        'image_max' => 'Görsel en fazla 5 MB olabilir.',
        'cut_contour_required' => 'En az bir kesim konturu (cut) ≥3 nokta içermeli.',
    ],

];
