<?php

// Bagisto (bayi portalı köprüsü) modülünün kullanıcıya dönük mesajları.
// Kullanım: __('bagisto::messages.anahtar'). Süper Admin › Dil Dosyaları'ndan düzenlenebilir.

return [

    // Bayi Sözleşmeleri
    'agreements' => [
        'published' => ':title için :version sürümü yayımlandı.',
        'unchanged' => 'Metin güncel sürümle aynı; yeni sürüm yayımlanmadı.',
        'draft_saved' => 'Taslak kaydedildi. Yayımlanana kadar bayi portalında eski sürüm gösterilir.',
        'draft_discarded' => 'Taslak silindi; yayımlanmış sürüme dönüldü.',
        'annex_created' => ':label eklendi. Metnini yazıp yayımlayın; ana metinde {{annex.:number}} ile anabilirsiniz.',
        'annex_retired' => ':label kaldırıldı. Onaylanmış kopyalar ve geçmiş sürümler korunur.',
        'annex_in_use' => ':label ana metnin güncel sürümünde anılıyor; önce atfı kaldırıp ana metni yayımlayın.',
        'annex_not_allowed' => 'Yalnızca ana sözleşmelere (uzun metin) ek eklenebilir.',
        'invalid_annex' => ':annex eki için onaylanan sürüm (:version) geçerli değil. Sayfayı yenileyip tekrar deneyin.',
        'published_reaccept' => ':title için :version sürümü yayımlandı. Mevcut bayiler portala girişte bu sürümü onaylayacak.',
        'settings_saved_addendum' => 'Kaydedildi. Ülke listesi değiştiği için Gönderim Kısıtlaması Ek Sözleşmesi :version yayımlandı; mevcut bayiler portala girişte onaylayacak.',
        'renewal_not_pending' => ':key için bekleyen bir onay yok ya da sürüm (:version) güncel değil. Sayfayı yenileyip tekrar deneyin.',
        'settings_saved' => 'Firma bilgileri ve özel etiketler kaydedildi.',
        'custom_tag_in_use' => 'Güncel bir sözleşmede kullanıldığı için silinemez: :tags. Önce metinden çıkarıp yeni sürüm yayımlayın.',
        'custom_tag_duplicate' => 'Aynı anahtarla iki özel etiket olamaz: :key.',
        'unknown_tags' => 'Tanımsız etiket: :tags. Yalnızca listedeki etiketleri kullanın.',
        'body_required' => 'Sözleşme metni boş olamaz.',
        'invalid_version' => ':title için onaylanan sürüm (:version) geçerli değil. Sayfayı yenileyip tekrar deneyin.',
    ],

];
