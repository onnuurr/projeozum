<?php

/**
 * Try-on prompt A/B ölçüm yüzeyi — SABİT test seti.
 *
 * {@see \Modules\Creative\Console\Commands\TryonPromptCompareCommand} tarafından
 * yüklenir. Bu dosyanın tek görevi veri sağlamaktır — hiçbir mantık, hiçbir AI
 * çağrısı içermez, saf bir PHP dizisi döner. Prompt DEĞİŞTİRİLMEDEN ÖNCE dondurulmuş
 * bir ölçüm yüzeyi olması amaçlanmıştır: iki prompt builder sürümü aynı girdilerle
 * çalıştırılıp çıktı metinleri (ve isteğe bağlı olarak gerçek görselleri)
 * karşılaştırılabilir.
 *
 * ==========================================================================
 * VERİ KAYNAĞI VE DÜRÜSTLÜK NOTU (2026-08-20, canlı Postgres'ten çekildi)
 * ==========================================================================
 * Bu vakalar gerçek `creative_tryon_results` / `creative_garment_scans` /
 * `creative_review_chats` satırlarından türetildi — UYDURULMADI. Ancak veri
 * tabanı beklenenden çok daha sığ çıktı, bu yüzden aşağıdaki sınırlamalar
 * BİLEREK ve AÇIKÇA not düşülüyor (kullanıcı onayı: "sadece gerçek 5-6 vaka +
 * dürüst boşluk notu" — sentetik/uydurma veri EKLENMEDİ):
 *
 * 1) `creative_tryon_results` tablosunda üretim anında sadece 2 (iki) canlı
 *    satır vardı (id=44, id=46) — ikisi de review_status=approved. Hiç
 *    `rejected` durumunda bir satır YOK.
 * 2) `review_tags` (ret-kodu etiketleme) kolonu hiçbir satırda hiç
 *    kullanılmamış — 21 `creative_rejection_reasons` kodundan HİÇBİRİNİN
 *    bir TryonResult'a bağlı gerçek bir örneği yok.
 * 3) `creative_review_chats` tablosunda 5 farklı geçmiş ret sohbeti (gerçek,
 *    Türkçe admin metni) var — ama bunların bağlı olduğu orijinal
 *    TryonResult satırları SİLİNMİŞ. Bu yüzden o üretimlerin gerçek
 *    extras/garment_scan/görsel bağlamı KURTARILAMADI; aşağıdaki chat_*
 *    vakalarında extras bağlamı olarak mevcut TEK zengin gerçek bağlam
 *    (result #44'ün garment_scan #11 + manken #8 + ürün görseli) yeniden
 *    kullanıldı — bu açıkça 'context_note' alanında işaretlidir.
 * 4) SONUÇ — 21 ret kodundan sadece şunlar gerçek metinle temsil ediliyor:
 *    #3 (Ağız/Gülüş), #6 (Vücut oranı), #7 (Işık), #8 (Duruş/Poz),
 *    #9 (Giysi uyumu), #15 (Kumaş dökümü/dokusu), #17 (Boy/kesim uyumu),
 *    #20 (Dikiş/kenar detayı).
 *    KAPSANMAYAN (gerçek örneği YOK — TODO, ileride gerçek ret verisi
 *    biriktikçe eklenmeli): #1 Göz, #2 Burun, #4 Saç, #5 Ten/Cilt,
 *    #10 Arka plan, #11 Giysi ön/arka karışıklığı, #12 Kol boyu/kesimi,
 *    #13 Yaka/kapüşon detayı, #14 Fermuar/düğme/çıtçıt, #16 Baskı/desen
 *    hizası, #18 Aksesuar eksik/fazla, #19 Renk uyumu,
 *    #21 Giysi-vücut ölçek uyumu.
 *
 * `extras` alanı, {@see \Modules\Creative\Services\GarmentIdentityRuleEngine::directivesFor()}
 * çıktısı ile AYNI şekildedir (label/statement/priority/protect) — yani
 * GeminiTryOnPromptBuilder::build()'a doğrudan verilebilir hale getirilmiş,
 * rule-engine'den GEÇMİŞ halidir (ham detection JSON'u değil).
 *
 * `model_image_path` / `garment_image_path` / extras içindeki `path` alanları
 * gerçek, hâlâ diskte var olan (2026-08-20 itibarıyla doğrulandı) dosyalara
 * işaret eder — sadece --mode=image için kullanılır, --mode=text bunlara
 * dokunmaz.
 */

return [

    // ----------------------------------------------------------------
    // Grup A — extras SAYISI çeşitliliği (1 / 3 / 7), garment_scan #4'ten
    // (7 tespitin TAMAMI source=manual, yani confidence=1.0, insan onaylı —
    // GarmentScanService::cropsForTryOn() bunları güvenilir sayar).
    // maxAutoCrops parametresi gerçek cropsForTryOn() çağrısına verilerek
    // üretildi (config default'u max_auto_crops=4'tür; 1/3/7 için bilerek
    // farklı limit geçildi — bu yüzden "gerçek bir TryonResult'ın birebir
    // kopyası" değil, "gerçek scan verisinden GERÇEK fonksiyonla türetilmiş
    // kontrollü dilim" olarak işaretleniyor).
    // ----------------------------------------------------------------

    'scan4_one_extra' => [
        'why' => 'extras=1 uç durumu. garment_scan #4 (7 gerçek insan-onaylı crop) üzerinde '
            . 'GarmentScanService::cropsForTryOn($scan, [], maxAutoCrops:1, ...) gerçek fonksiyonu '
            . 'çalıştırılıp ilk (en yüksek confidence) crop alındı. protectListSentence tetiklenmiyor '
            . '(bu tekil crop\'un default_priority\'si medium) — extras=1 + protect yok kombinasyonunu ölçer.',
        'source' => 'creative_garment_scans#4 (gerçek DB, 2026-08-20 çekildi); GarmentScanService::cropsForTryOn(maxAutoCrops:1) ile türetildi',
        'context_note' => null,
        'rejection_reason_codes' => [],
        'extras' => [
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/2c1e7fc3-9054-46cd-9d7b-c57503bc8026.png', 'label' => 'Etek fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
        ],
        'protect_list_sentence' => null,
        'extra_instruction' => null,
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97.png',
    ],

    'scan4_three_extras' => [
        'why' => 'extras=3 orta durumu. Aynı scan #4, cropsForTryOn(maxAutoCrops:3, ...) gerçek '
            . 'fonksiyon çağrısıyla türetildi. Yine protect yok — extras=1 ile extras=7 (protect VAR) '
            . 'arasında bir ara nokta olarak, prompt uzunluğunun describeExtras() bölümünde nasıl '
            . 'büyüdüğünü ölçmek için var.',
        'source' => 'creative_garment_scans#4 (gerçek DB); GarmentScanService::cropsForTryOn(maxAutoCrops:3) ile türetildi',
        'context_note' => null,
        'rejection_reason_codes' => [],
        'extras' => [
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/2c1e7fc3-9054-46cd-9d7b-c57503bc8026.png', 'label' => 'Etek fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/2ffbf248-bf5f-4cf3-8b7f-54bf755742b6.png', 'label' => 'Pileli etek ucu', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/92f98638-f065-4124-addd-526e9dd4794d.png', 'label' => 'Kol fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
        ],
        'protect_list_sentence' => null,
        'extra_instruction' => null,
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97.png',
    ],

    'scan4_seven_extras_protect' => [
        'why' => 'extras=7 + protectListSentence TETİKLENEN uç durum. Aynı scan #4'
            . "'ün TÜM 7 crop'u — cropsForTryOn(maxAutoCrops:7, ...) gerçek çağrısıyla. 7. crop "
            . "('Yaka') GarmentLabel taban önceliği HIGH olduğundan GarmentIdentityRuleEngine onu "
            . "protect=true işaretliyor ve protectListSentence prompt'un EN BAŞINA ekleniyor. Bu, "
            . 'kod tabanındaki en yoğun describeExtras() + protect çapası senaryosu.',
        'source' => 'creative_garment_scans#4 (gerçek DB, tüm 7 crop); GarmentScanService::cropsForTryOn(maxAutoCrops:7) + GarmentIdentityRuleEngine::directivesFor() ile türetildi',
        'context_note' => null,
        'rejection_reason_codes' => [],
        'extras' => [
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/2c1e7fc3-9054-46cd-9d7b-c57503bc8026.png', 'label' => 'Etek fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/2ffbf248-bf5f-4cf3-8b7f-54bf755742b6.png', 'label' => 'Pileli etek ucu', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/92f98638-f065-4124-addd-526e9dd4794d.png', 'label' => 'Kol fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/d94c996a-8ca6-408e-a496-031e1c4b0f5e.png', 'label' => 'Sol kol fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/7a88da4d-7e07-4460-8810-f7bb1f428858.png', 'label' => 'Göğüs dikiş fırfır detayı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/5c91f932-a280-414a-8036-f36770eb42f9.png', 'label' => 'Kol uçları lastik fırfırı', 'statement' => null, 'priority' => 'medium', 'protect' => false],
            ['path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97/8546ae36-db1e-48dc-8992-3998dadbdb7e.png', 'label' => 'Yaka', 'statement' => "For the 'Yaka' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Yaka.',
        'extra_instruction' => null,
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'garment-scans/45287cd60e722768ad448f4e6f8c567679683c97.png',
    ],

    // ----------------------------------------------------------------
    // Grup B — İKİ CANLI, tam bağlamlı gerçek TryonResult satırı. Hiçbir
    // alan yeniden bağlanmadı/uydurulmadı — hepsi o satırın kendi
    // meta.garment_extras + kendi garment_scan_id'sinden, gerçek
    // ProductOnModelService::generate() akışının aynısı çalıştırılarak
    // (GarmentScanService::cropsForTryOn + GarmentIdentityRuleEngine)
    // türetildi.
    // ----------------------------------------------------------------

    'result_44_seven_mixed_extras' => [
        'why' => "Gerçek TryonResult #44 (product #1, approved). 6 manuel açı/detay görseli "
            . "meta.garment_extras'tan — bunların label_key'i YOK, yani GarmentIdentityRuleEngine "
            . "'eski usul manuel yükleme' (G.1-G.3) dalına düşüyor: statement/priority/protect hiç "
            . "eklenmiyor. + garment_scan #11'in TEK insan-onaylı ('manual', source=manual) auto-crop'u "
            . "('Puf Kol', taban önceliği HIGH → protect=true). Amaç: aynı vakada HEM eski-usul HEM "
            . 'rule-engine\'li extras\'ın bir arada bulunduğu, gerçek ve en yoğun (7 extras) canlı '
            . 'senaryoyu ölçmek.',
        'source' => 'creative_tryon_results#44 + creative_garment_scans#11 (gerçek DB, 2026-08-20)',
        'context_note' => null,
        'rejection_reason_codes' => [],
        'extras' => [
            ['path' => 'creative/tryon_garments/IxIQhinGOjFr0ylZ2z4JmIo6wwhrOKLkfSqXqMz0.jpg', 'label' => 'Arkadan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/O3ViapoKKNlJAimtW50hZmibdhdtTen03nhDrGQG.jpg', 'label' => 'Yandan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/wtVwROUoIVIwtrSBoEWEUTnohC868nzy8jwXy5Ph.jpg', 'label' => 'Yaka / Dikiş Detayı', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/qR3fzYKg5PPtR9VrJzVbV29RVjaiRescpOECK9cH.jpg', 'label' => 'Kumaş Detayı', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/iQItmveyV4ujre0IGmAyKFseSX5ME4CKmqb3FlPJ.jpg', 'label' => 'Etek ucu', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/qba37IVhpamHzrg7VO9nI5dPfrADOMs8bez5DghT.jpg', 'label' => 'Kapişon', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => null,
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'creative/tryon_garments/78N5uRpwg1XZ16JgSLANgkkWRx2P9c7eM4CmchA1.jpg',
    ],

    'result_46_five_mixed_extras' => [
        'why' => "Gerçek TryonResult #46 (product #1, approved, aynı garment_scan #11 ama farklı "
            . 'manken/poz — mannequin #9). 4 manuel (eski-usul, label_key yok) + aynı 1 rule-engine\'li '
            . "auto-crop ('Puf Kol', protect=true). extras=5 orta-üst yoğunluk, FARKLI bir manken "
            . 'referans görseliyle — model kimliği değişince prompt metninin DEĞİŞMEYEN kısmını '
            . '(kimlik-koruma cümleleri sabit kalmalı) doğrulamak için result_44 ile birlikte kullanılır.',
        'source' => 'creative_tryon_results#46 + creative_garment_scans#11 (gerçek DB, 2026-08-20)',
        'context_note' => null,
        'rejection_reason_codes' => [],
        'extras' => [
            ['path' => 'creative/tryon_garments/izHgru4TS9bNLKfZDRy6yKArornLtUXw8nZ4nmPh.jpg', 'label' => 'Arkadan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/1fBGkdmXPQphXOwoKO1A3h3RnFvb1r5Ry81wI54i.jpg', 'label' => 'Yaka / Dikiş Detayı', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/c1myVPk5sUGC8R6F1arfMTqlCxFDWIG4oez5v8Mm.jpg', 'label' => 'Yakın Çekim', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/AHdbDjf2na36P3Ekyc2J2tLcsUfApLseq0RKsZvc.jpg', 'label' => 'etek ucu', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => null,
        'model_image_path'   => 'mannequins/9/reference.png',
        'garment_image_path' => 'creative/tryon_garments/RHtOC4xI2KEtO6tLyJQa9okiy5FgIeoocFZHfl51.jpg',
    ],

    // ----------------------------------------------------------------
    // Grup C — GERÇEK ret sohbetlerinden (creative_review_chats, 5 farklı
    // subject_id) türetilen extraInstruction vakaları. ÖNEMLİ SINIRLAMA:
    // bu 5 sohbetin bağlı olduğu orijinal TryonResult satırları silinmiş,
    // orijinal extras/görsel bağlamı KURTARILAMADI. Bu yüzden extras bağlamı
    // olarak result_44'ün gerçek bağlamı (garment_scan #11 + manken #8 +
    // ürün görseli) yeniden kullanıldı — context_note alanı bunu açıkça
    // belirtir. extra_instruction metni, review_chats'teki GERÇEK Türkçe
    // admin geri bildiriminden, sistemin kendisinin de yaptığı dönüşümü
    // (ReviewChatService → Gemini ile TR sohbetten EN FINAL_INSTRUCTION
    // üretimi) elle uygulayarak İngilizceye çevrildi — orijinal EN çevirisi
    // (meta.extra_instructions) satır silindiği için kurtarılamadı.
    // ----------------------------------------------------------------

    'chat3_lower_half_missing' => [
        'why' => 'Gerçek ret vakası (creative_review_chats, subject_id=3, 2026-07-15). Admin: '
            . "\"tulumun alt tarafı da var ama oluşan görselde altı oluşmamış\" (tulum/elbisenin alt "
            . 'yarısı hiç render edilmemiş). Ret kodu eşlemesi: #9 Giysi uyumu / #17 Boy-kesim uyumu. '
            . 'Bu, extraInstruction\'ın prompt SONUNA "IMPORTANT correction" olarak eklendiği en temel '
            . 'senaryo.',
        'source' => 'creative_review_chats#1-4 (subject_id=3, gerçek DB metni) — orijinal TryonResult#3 satırı silinmiş',
        'context_note' => "extras bağlamı orijinalinden DEĞİL — result_44'ün gerçek bağlamı (aşağıya bkz.) yeniden kullanıldı, çünkü orijinal üretimin extras/görsel verisi artık DB'de yok.",
        'rejection_reason_codes' => [9, 17],
        'extras' => [
            ['path' => 'creative/tryon_garments/IxIQhinGOjFr0ylZ2z4JmIo6wwhrOKLkfSqXqMz0.jpg', 'label' => 'Arkadan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'creative/tryon_garments/O3ViapoKKNlJAimtW50hZmibdhdtTen03nhDrGQG.jpg', 'label' => 'Yandan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => "The garment's lower half (the trousers/skirt portion of this one-piece jumpsuit/dress) was completely missing or not rendered in the previous attempt — render the complete garment including its full lower section exactly as shown in the reference photo.",
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'creative/tryon_garments/78N5uRpwg1XZ16JgSLANgkkWRx2P9c7eM4CmchA1.jpg',
    ],

    'chat9_sleeve_floating' => [
        'why' => 'Gerçek ret vakası (creative_review_chats, subject_id=9, 2026-07-15). Admin review_note '
            . "(asistan yanıtında alıntılanmış): \"kolların sonradan takılmış gibi durması, gerçekçilikten "
            . 'çok uzak\", işaretlenen alanlar: Vücut oranı (#6), Duruş/Poz (#8). Bu vaka özellikle önemli: '
            . 'GeminiTryOnPromptBuilder\'a 2026-08-19\'da eklenen "anatomi/oran sadakati" cümlesi '
            . '(sleeves/cuffs kol-dirsek-bilek bağlantısı) TAM OLARAK bu şikayeti hedefliyor — yani bu, '
            . 'prompt\'un GERÇEKTEN bir geçmiş regresyonu düzeltip düzeltmediğini ölçen bir regresyon '
            . 'vakası.',
        'source' => 'creative_review_chats#5-6 (subject_id=9, gerçek DB metni) — orijinal TryonResult#9 satırı silinmiş',
        'context_note' => "extras bağlamı orijinalinden DEĞİL — result_44'ün gerçek bağlamı yeniden kullanıldı.",
        'rejection_reason_codes' => [6, 8],
        'extras' => [
            ['path' => 'creative/tryon_garments/IxIQhinGOjFr0ylZ2z4JmIo6wwhrOKLkfSqXqMz0.jpg', 'label' => 'Arkadan', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => 'In the previous attempt, the arms/sleeves looked like they were attached afterward and not physically connected to the body — fix the shoulder-to-arm attachment and overall body proportion so the limbs look naturally part of the same photograph.',
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'creative/tryon_garments/78N5uRpwg1XZ16JgSLANgkkWRx2P9c7eM4CmchA1.jpg',
    ],

    'chat18_child_proportions_light_smile' => [
        'why' => 'Gerçek ret vakası (creative_review_chats, subject_id=18, 2026-07-16). Admin geri '
            . "bildirimi (asistan yanıtından): \"3 yaşındaki modelin göbek orantısı, gülüşünün doğallığı "
            . 've stüdyo ışıklandırması konularında iyileştirme yapmamız gerekiyor\". Üç farklı sorunu '
            . 'TEK cümlede birleştiren, extraInstruction\'ın birden fazla düzeltmeyi aynı anda taşıdığı '
            . 'gerçekçi bir vaka (Vücut oranı #6, Ağız/Gülüş #3, Işık #7).',
        'source' => 'creative_review_chats#7-8 (subject_id=18, gerçek DB metni) — orijinal TryonResult#18 satırı silinmiş',
        'context_note' => "extras bağlamı orijinalinden DEĞİL — result_46'nın gerçek bağlamı yeniden kullanıldı.",
        'rejection_reason_codes' => [3, 6, 7],
        'extras' => [
            ['path' => 'creative/tryon_garments/1fBGkdmXPQphXOwoKO1A3h3RnFvb1r5Ry81wI54i.jpg', 'label' => 'Yaka / Dikiş Detayı', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => "For this 3-year-old child model, the previous attempt had an unnatural belly/torso proportion, an unnatural forced smile, and flat studio lighting — correct the child's body proportions, render a natural relaxed smile, and use proper soft studio lighting with realistic shadows.",
        'model_image_path'   => 'mannequins/9/reference.png',
        'garment_image_path' => 'creative/tryon_garments/RHtOC4xI2KEtO6tLyJQa9okiy5FgIeoocFZHfl51.jpg',
    ],

    'chat24_fabric_hallucinated_details' => [
        'why' => 'Gerçek ret vakası (creative_review_chats, subject_id=24, 2026-07-18). Admin\'in KENDİ '
            . "yazdığı ham metin (rol=user, hiç asistan paraphrase'i değil): \"Pantolonun kumaşı gerçek "
            . 'üründen farklı çıkmış, gereksiz dikiş/cep detayları eklenmiş. Düz kumaş olmalı, sade '
            . "dursun.\" Bu, prompt'un merkezi sadakat maddesiyle (\"do not redesign... invent a "
            . 'different product") doğrudan çakışan, modelin ÜRÜNDE OLMAYAN detay (dikiş/cep) uydurduğu '
            . 'gerçek bir vaka — kod tabanındaki en kritik hallucination-tipi hata modunu test eder '
            . '(Kumaş dökümü/dokusu #15, Dikiş/kenar detayı #20).',
        'source' => 'creative_review_chats#9 (subject_id=24, gerçek DB metni, admin\'in kendi yazdığı) — orijinal TryonResult#24 satırı silinmiş',
        'context_note' => "extras bağlamı orijinalinden DEĞİL — result_44'ün gerçek bağlamı yeniden kullanıldı.",
        'rejection_reason_codes' => [15, 20],
        'extras' => [
            ['path' => 'creative/tryon_garments/qR3fzYKg5PPtR9VrJzVbV29RVjaiRescpOECK9cH.jpg', 'label' => 'Kumaş Detayı', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => 'The previous attempt rendered the fabric differently from the real product and invented extra stitching/pocket details that do not exist on the reference garment — the fabric must be plain and clean exactly as shown in the reference photo, with no added seams or pockets.',
        'model_image_path'   => 'mannequins/8/reference.png',
        'garment_image_path' => 'creative/tryon_garments/78N5uRpwg1XZ16JgSLANgkkWRx2P9c7eM4CmchA1.jpg',
    ],

    'chat35_head_body_ratio' => [
        'why' => 'Gerçek ret vakası (creative_review_chats, subject_id=35, 2026-08-18 — en güncel gerçek '
            . "vaka). Admin geri bildirimi (asistan yanıtından): \"3 yaşındaki Ahmet'in vücut ve kafa "
            . "oranlarının doğal görünmediği, sanki sonradan eklenmiş gibi durduğu\". chat9_sleeve_floating "
            . "ile aynı kök nedene (anatomi/oran sadakati) işaret ediyor ama kafa-vücut oranı özelinde — "
            . "GeminiTryOnPromptBuilder'daki \"head-to-body ratio... never lengthen, shorten, or distort a "
            . 'limb, the neck or the head\" cümlesini doğrudan hedefleyen ikinci, bağımsız gerçek örnek '
            . '(Vücut oranı #6).',
        'source' => 'creative_review_chats#13-14 (subject_id=35, gerçek DB metni) — orijinal TryonResult#35 satırı silinmiş',
        'context_note' => "extras bağlamı orijinalinden DEĞİL — result_46'nın gerçek bağlamı yeniden kullanıldı.",
        'rejection_reason_codes' => [6],
        'extras' => [
            ['path' => 'creative/tryon_garments/AHdbDjf2na36P3Ekyc2J2tLcsUfApLseq0RKsZvc.jpg', 'label' => 'etek ucu', 'statement' => null, 'priority' => null, 'protect' => false],
            ['path' => 'garment-scans/63c04956d1a96092b9d0889c196b1af512c7e7ca/5c9b8228-4a4e-4e8f-b7ef-3e0f4d13c0c7.png', 'label' => 'Puf Kol', 'statement' => "For the 'Puf Kol' part (HIGH — do not redesign, replace, or invent): preserve exactly as shown in the reference image.", 'priority' => 'high', 'protect' => true],
        ],
        'protect_list_sentence' => 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each exactly as shown in its reference image: Puf Kol.',
        'extra_instruction' => 'For this 3-year-old child model, the head-to-body proportion in the previous attempt looked unnatural, as if the head were added afterward — render a natural, correctly proportioned head-to-body ratio for a 3-year-old child, with the head naturally and continuously integrated into the body.',
        'model_image_path'   => 'mannequins/9/reference.png',
        'garment_image_path' => 'creative/tryon_garments/RHtOC4xI2KEtO6tLyJQa9okiy5FgIeoocFZHfl51.jpg',
    ],

];
