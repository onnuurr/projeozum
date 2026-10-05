<?php

// Creative modülünün kullanıcıya dönük uyarı, hata ve başarı mesajları.
// Kullanım: __('creative::messages.anahtar'). Süper Admin › Dil Dosyaları'ndan düzenlenebilir.

return [

    // Ortak inceleme (onay/ret) kuralları
    'review' => [
        'own_work' => 'Kendi ürettiğiniz görseli onaylayamaz/reddedemezsiniz.',
        'reason_required' => 'En az bir düzeltme alanı seçin veya en az 10 karakterlik açıklama yazın.',
        'default_reason_category' => 'Düzeltilmesi gereken alan',
    ],

    // AI sağlayıcısı (Gemini) hataları — GeminiRequestException; ham cevap yalnızca loga yazılır.
    // Creative'in paylaşılan AI altyapısını kullanan modüller (Product açıklama vb.) de bu metinleri gösterir.
    'ai_provider' => [
        'missing_key' => 'AI sağlayıcısının API anahtarı tanımlı değil. Sistem yöneticisine bildirin.',
        'auth' => 'AI sağlayıcısı API anahtarını kabul etmedi (geçersiz ya da silinmiş anahtar). Sistem yöneticisine bildirin.',
        'denied' => 'AI sağlayıcısı bu hesabın erişimini reddetti. Sağlayıcı hesabında bir kısıtlama olabilir; sistem yöneticisine bildirin.',
        'quota' => 'AI sağlayıcısının kullanım kotası doldu. Birkaç dakika sonra tekrar deneyin; sürerse sistem yöneticisine bildirin.',
        'unavailable' => 'AI sağlayıcısı şu anda yanıt veremiyor (yoğunluk ya da bakım). Biraz sonra tekrar deneyin.',
        'unreachable' => 'AI sağlayıcısına bağlanılamadı ya da istek zaman aşımına uğradı. Biraz sonra tekrar deneyin.',
        'bad_request' => 'AI sağlayıcısı isteği geçersiz buldu. Girdi ya da ayarlar sağlayıcının beklediği biçimde değil.',
        'blocked' => 'AI sağlayıcısının içerik güvenlik filtresi yanıtı engelledi. Girdiyi değiştirip tekrar deneyin.',
        'empty_response' => 'AI sağlayıcısı boş yanıt döndürdü. Tekrar deneyin.',
        'file_missing' => 'AI\'ya gönderilecek görsel dosyası bulunamadı.',
        'failed' => 'AI sağlayıcısına yapılan istek başarısız oldu. Tekrar deneyin; sürerse sistem yöneticisine bildirin.',
    ],

    // Kuyruk işleri (ekranda hata olarak görünür)
    'job_attempt_failed' => 'Deneme :attempt/:tries başarısız: :error',

    // Creative Studio
    'studio' => [
        'queued' => ':count görsel üretim kuyruğuna alındı.',
        'nothing_to_download' => 'İndirilecek onaylı görsel yok.',
        'zip_failed' => 'ZIP oluşturulamadı.',
        'not_pending' => 'Bu görsel onay bekliyor durumda değil.',
        'regenerating_cannot_approve' => 'Görsel yeniden üretiliyor, işlem tamamlanana kadar onaylanamaz.',
        'regenerating_cannot_reject' => 'Görsel yeniden üretiliyor, işlem tamamlanana kadar reddedilemez.',
        'approved' => 'Görsel onaylandı.',
        'rejected' => 'Görsel reddedildi.',
        'regenerate_queued' => 'Görsel yeniden üretim kuyruğuna alındı.',
        'caption_updated' => 'Caption güncellendi.',
    ],

    // Mankenler
    'mannequin' => [
        'queued' => 'Manken üretim kuyruğuna alındı.',
        'regenerate_queued' => 'Manken yeniden üretim kuyruğuna alındı.',
        'not_pending' => 'Bu manken onay bekliyor durumda değil.',
        'approved' => 'Manken onaylandı.',
        'rejected' => 'Manken reddedildi.',
        'deleted' => 'Manken silindi.',
        'image_unreadable' => 'Manken görseli okunamadı.',
    ],

    // Pozlar
    'pose' => [
        'previews_queued' => ':count poz önizlemesi üretim kuyruğuna alındı.',
        'created' => 'Poz eklendi, önizleme kuyruğa alındı.',
        'regenerate_queued' => 'Poz önizlemesi yeniden üretim kuyruğuna alındı.',
        'deleted' => 'Poz silindi.',
        'preview_unreadable' => 'Poz önizleme görseli okunamadı.',
    ],

    // Giydirme (try-on)
    'mockup' => [
        'unsupported_category' => '":category" kategorisi mokap giydirmede tanımlı değil (dış giyim, aksesuar vb. mokap dışıdır).',
        'no_category' => 'Ürünün kategorisi yok; mokap için bölge tipi belirlenemedi.',
        'no_product_photo' => 'Ürünün giydirilecek yalın (mankensiz) fotoğrafı yok. Ürüne fotoğraf ekleyin ya da giysi görseli verin.',
        'garment_not_found' => 'Giysi görseli bulunamadı: ":path". Tam yol (/home/...), proje kökünden yol (public/...) ya da medya diskindeki yol verin.',
        'mannequin_not_ready' => 'Manken hazır ve onaylı değil.',
        'no_master_pose' => 'Ana kare için hazır poz bulunamadı.',
        'master_failed' => 'Ana kare üretilemedi: :error',
        'no_ready_base' => 'Hiç hazır baz görsel yok; giydirme kuyruğa alınamadı.',
        'base_missing' => 'Giydirmenin baz görseli bulunamadı.',
        'stylist_invalid' => 'Stilist geçerli bir kombin önerisi döndürmedi.',
        'vision_failed' => 'Görüntü işleme adımı (:step) başarısız: :error',
        'queued' => ':product için mokap giydirme kuyruğa alındı.',
    ],
    'closeup' => [
        'source_not_approved' => 'Yakın plan yalnızca onaylanmış bir tam boy giydirmeden üretilebilir.',
        'source_missing' => 'Yakın planın kaynağı olan giydirme görseli bulunamadı.',
        'unknown_shot' => 'Tanımsız yakın plan kadrajı: :shot',
        'queued' => ':count yakın plan kuyruğa alındı.',
    ],
    'tryon' => [
        'no_garment_image' => 'Bu ürünün giydirilecek bir fotoğrafı yok. Önce ürüne fotoğraf ekleyin ya da burada bir görsel yükleyin.',
        'no_pose_ready' => 'Seçili pozların hiçbiri hazır değil.',
        'queued' => ':count görsel giydirme kuyruğuna alındı.',
        'not_pending' => 'Bu sonuç onay bekliyor durumda değil.',
        'approved' => 'Giydirme görseli onaylandı ve ürüne eklendi.',
        'rejected' => 'Giydirme görseli reddedildi.',
        'cannot_be_cover' => 'Bu sonuç henüz kapak yapılamaz.',
        'cover_updated' => 'Ürün kapağı güncellendi.',
        'deleted' => 'Giydirme sonucu silindi.',
        'product_not_found' => 'Giydirme için ürün bulunamadı.',
        'mannequin_not_ready' => 'Manken hazır değil; önce manken kimlik görseli üretilmeli.',
        'pose_not_ready' => 'Seçili poz hazır değil.',
        'mannequin_reference_missing' => 'Manken referans görseli diskte bulunamadı.',
        'product_has_no_image' => 'Ürünün giydirilecek bir görseli yok.',
        'product_image_unresolvable' => 'Ürün görseli yerel yola çözülemedi.',
        'nothing_to_approve' => 'Onaylanacak bir görsel yok; önce üretim tamamlanmalı.',
        'output_unreadable' => 'Giydirme çıktısı okunamadı.',
        'output_write_failed' => 'Giydirme çıktısı diske yazılamadı: :path (izin sorunu olabilir).',
    ],

    // Giysi tarama / parça etiketleme
    'garment' => [
        'part_added' => 'Parça eklendi.',
        'part_not_found' => 'Güncellenecek parça bulunamadı.',
        'part_updated' => 'Parça güncellendi.',
        'part_deleted' => 'Parça silindi.',
        'scan_unreadable' => 'Giysi görseli taramak için okunamadı: :path',
        'source_write_failed' => 'Giysi kaynak görseli diske yazılamadı: :path',
    ],

    // Şablonlar
    'template' => [
        'generate_failed' => 'Şablon üretilemedi (Python/resvg kurulu mu?).',
        'generated_partial' => ':created şablon üretildi, :failed format başarısız oldu.',
        'generated' => ':created şablon üretildi.',
        'uploaded_slots_failed' => 'Şablon yüklendi ancak slotlar çıkarılamadı (Python/resvg kurulu mu?): :error',
        'uploaded' => 'Şablon yüklendi ve slotlar çıkarıldı.',
        'updated' => 'Şablon güncellendi.',
        'slots_save_failed' => 'Slotlar kaydedilemedi (Python kurulu mu?): :error',
        'slots_saved' => 'Şablon slotları kaydedildi.',
        'deleted' => 'Şablon silindi.',
        'svg_not_found' => 'Şablon SVG bulunamadı: :path',
        'asset_incomplete' => 'Asset için şablon veya ürün bulunamadı.',
        'ai_compose_requires_ocr' => 'AI kompozisyon motoru (ai_compose) OCR doğrulaması olmadan çalıştırılamaz. creative.composition.ocr.enabled=true yapın.',
        'unknown_preset' => 'Bilinmeyen şablon preset\'i: :preset',
    ],

    // Marka kitleri
    'brand_kit' => [
        'created' => 'Marka kiti oluşturuldu.',
        'updated' => 'Marka kiti güncellendi.',
        'deleted' => 'Marka kiti silindi.',
        'zip_has_no_font' => 'ZIP içinde font (ttf/otf/woff/woff2) bulunamadı.',
        'unsupported_font' => 'Desteklenmeyen dosya türü. ttf/otf/woff/woff2 veya .zip yükleyin.',
    ],

    // İş ortağı kreatif profilleri
    'partner_brand_kit' => [
        'created' => 'İş ortağı kreatif profili oluşturuldu.',
        'updated' => 'İş ortağı kreatif profili güncellendi.',
        'deleted' => 'İş ortağı kreatif profili silindi.',
    ],

    // Ret seçim maddeleri
    'rejection_reason' => [
        'created' => 'Ret seçim maddesi eklendi.',
        'updated' => 'Ret seçim maddesi güncellendi.',
        'deleted' => 'Ret seçim maddesi kaldırıldı.',
    ],

    // İnceleme sohbeti
    'review_chat' => [
        'sent' => 'Mesaj gönderildi.',
        'no_instruction' => 'Henüz uygulanabilir bir düzeltme talimatı yok; sohbete devam edin.',
        'applied_mannequin' => 'Talimat uygulandı, manken yeniden üretim kuyruğuna alındı.',
        'applied_image' => 'Talimat uygulandı, görsel yeniden üretim kuyruğuna alındı.',
        'forbidden' => 'Bu sohbete erişim yetkiniz yok.',
        'status_changed' => 'Görselin durumu değiştiği için sohbet kullanılamıyor.',
    ],

    // Ret analiz raporları
    'report_not_found' => 'Rapor bulunamadı: :file',

    // Kompozisyon / yerleşim kalite kontrolü (şablon ve üretim ihlalleri)
    'constraints' => [
        'headline_words' => 'headline kelime sayısı :count, izin verilen azami :max',
        'headline_lines' => 'headline satır sayısı :count, izin verilen azami :max',
        'cta_words' => 'CTA kelime sayısı :count, izin verilen azami :max',
        'headline_ocr_mismatch' => 'headline OCR uyuşmazlığı (":text" bulunamadı, benzerlik :similarity < :min)',
        'headline_outside_margin' => 'headline metni safe-margin dışında konumlanmış',
        'cta_missing' => 'CTA metni zorunlu ama üretilmedi',
        'cta_ocr_mismatch' => 'CTA OCR uyuşmazlığı (":text" bulunamadı, benzerlik :similarity < :min)',
        'cta_outside_margin' => 'CTA metni safe-margin dışında konumlanmış',
        'ocr_empty' => 'OCR hiçbir metin bulamadı (üretim/tanıma hatası olabilir)',
        'duplicate_slot' => '":key" anahtarı :count kez tekrarlanmış (yinelenen slot)',
        'hero_coverage' => 'ürün görseli tuvalin yalnız %:pct\'ini kaplıyor (asgari %:min)',
        'whitespace' => 'boşluk oranı %:pct (asgari %:min)',
        'slots_overlap' => '":a" ve ":b" slotları çakışıyor',
        'logo_slot_missing' => '\'logo\' anahtarlı bir slot tanımlanmamış',
    ],

];
