<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bayi portalının (Bagisto) koda gömülü sözleşme/onay metinleri SaaS'a taşınır.
 *
 * - Çerçeve sözleşme: Bagisto'da kullanılan metin, bayilerin bugüne kadar onayladığı sürüm numarasıyla
 *   (2025/v2.1) geçmiş kaydı olarak eklenir; ardından Madde 4'ü peşin kart kararına göre düzeltilmiş
 *   metin 2026/v1 olarak yayımlanır.
 * - Taahhütname, ticari ileti izni, platform kargo şartları: Bagisto'daki onay kutusu metinleri.
 * - Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi: Bagisto'da metni hiç yoktu (CMS sayfası
 *   açılmamıştı); kayıtları açılır, metin yayımlanana kadar ödeme ekranında bağlantısız gösterilir.
 *
 * Kendi içinde kapalıdır (servis sınıflarına bağlı değil); kayıtlar varsa dokunmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $agreements = [
            'framework' => [
                'title' => 'Ozm Kids B2B Bayilik Çerçeve Sözleşmesi',
                'display' => 'document',
                'description' => 'Resmi belge adımında ve üyelik tamamlamada onaylatılır.',
                'sort_order' => 1,
                'versions' => [
                    [
                        'version' => '2025/v2.1',
                        'title' => 'Bomonti Atölye toptan & dropshipping çerçeve sözleşmesi',
                        'body' => implode("\n\n", [
                            '**Madde 1 – Taraflar ve kapsam:** İşbu sözleşme, Ozm Kids ile yetkili bayi adayı arasında toptan serili ürün alımı ve/veya dropshipping konsinye satış operasyonlarını düzenler.',
                            '**Madde 2 – Asgari satış fiyatı koruma politikası:** Bayi, Ozm Kids markalı ürünleri sistemde belirtilen tavsiye edilen perakende satış fiyatının %15\'inden daha düşük fiyata hiçbir platformda satamaz.',
                            '**Madde 3 – Orijinal ambalaj ve etiket:** Ürünler orijinal etiketleri, barkodları ve ambalajıyla son kullanıcıya ulaştırılır.',
                            '**Madde 4 – İade ve mahsup:** Yalnızca üretim kaynaklı kusurlu ürünler için iade kabul edilir; iade, atölye ekspertizi onayından sonra sonuçlanır.',
                        ]),
                        'change_note' => 'Bayi portalında koda gömülü kullanılan metin (SaaS\'a taşındı).',
                    ],
                    [
                        'version' => '2026/v1',
                        'title' => 'Ozm Kids B2B Bayilik Çerçeve Sözleşmesi',
                        'body' => implode("\n\n", [
                            '**Madde 1 – Taraflar ve kapsam:** İşbu sözleşme, Ozm Kids ile yetkili bayi adayı arasında toptan serili ürün alımı ve/veya dropshipping konsinye satış operasyonlarını düzenler.',
                            '**Madde 2 – Asgari satış fiyatı koruma politikası:** Bayi, Ozm Kids markalı ürünleri sistemde belirtilen tavsiye edilen perakende satış fiyatının %15\'inden daha düşük fiyata hiçbir platformda satamaz.',
                            '**Madde 3 – Orijinal ambalaj ve etiket:** Ürünler orijinal etiketleri, barkodları ve ambalajıyla son kullanıcıya ulaştırılır.',
                            '**Madde 4 – İade:** Yalnızca üretim kaynaklı kusurlu ürünler için iade kabul edilir; atölye ekspertizi onayından sonra tutar, siparişte kullanılan ödeme yöntemine (kredi kartına) iade edilir.',
                        ]),
                        'change_note' => 'Madde 4: cari mahsup yerine kredi kartına iade (ödemeler peşin kredi kartı).',
                    ],
                ],
            ],
            'declaration' => [
                'title' => 'Bayilik Taahhütnamesi',
                'display' => 'consent',
                'description' => 'Ön başvuru formundaki zorunlu onay kutusu.',
                'sort_order' => 2,
                'versions' => [[
                    'version' => '2026/v1',
                    'title' => 'Bayilik Taahhütnamesi',
                    'body' => '**Bayilik taahhütnamesi:** Başvuru formunda beyan ettiğim firma, VKN ve iletişim bilgilerinin doğru olduğunu ve firmanın yetkili temsilcisi olduğumu kabul ederim; bu bilgilerin başvurunun değerlendirilmesi için işlenmesine onay veririm.',
                    'change_note' => 'Bayi portalındaki metin SaaS\'a taşındı.',
                ]],
            ],
            'marketing' => [
                'title' => 'Ticari Elektronik İleti İzni',
                'display' => 'consent',
                'description' => 'Ön başvuru formundaki isteğe bağlı onay kutusu.',
                'sort_order' => 3,
                'versions' => [[
                    'version' => '2026/v1',
                    'title' => 'Ticari Elektronik İleti İzni',
                    'body' => 'KVKK kapsamında toptan fiyat listeleri, yeni sezon kumaş kartelaları ve stok bildirimleri için ticari elektronik ileti (SMS ve e-posta) almayı onaylıyorum.',
                    'change_note' => 'Bayi portalındaki metin SaaS\'a taşındı.',
                ]],
            ],
            'shipping_terms' => [
                'title' => 'Platform Kargo Anlaşması Şartları',
                'display' => 'consent',
                'description' => 'Üyelik tamamlamada platform kargo anlaşmasını seçen bayiye onaylatılır.',
                'sort_order' => 4,
                'versions' => [[
                    'version' => '2026/v1',
                    'title' => 'Platform Kargo Anlaşması Şartları',
                    'body' => 'Ozm Kids kargo anlaşmasıyla gönderilen siparişlerde gönderici bilgisinin platforma ait olacağını kabul ediyorum.',
                    'change_note' => 'Bayi portalındaki metin SaaS\'a taşındı.',
                ]],
            ],
            'pre_information' => [
                'title' => 'Ön Bilgilendirme Formu',
                'display' => 'document',
                'description' => 'Ödeme ekranında onaylatılır. Yayımlanana kadar bağlantısız gösterilir.',
                'sort_order' => 5,
                'versions' => [],
            ],
            'distance_sales' => [
                'title' => 'Mesafeli Satış Sözleşmesi',
                'display' => 'document',
                'description' => 'Ödeme ekranında onaylatılır. Yayımlanana kadar bağlantısız gösterilir.',
                'sort_order' => 6,
                'versions' => [],
            ],
        ];

        DB::transaction(function () use ($agreements, $now) {
            foreach ($agreements as $key => $agreement) {
                if (DB::table('dealer_agreements')->where('key', $key)->exists()) {
                    continue;
                }

                $id = DB::table('dealer_agreements')->insertGetId([
                    'key' => $key,
                    'title' => $agreement['title'],
                    'display' => $agreement['display'],
                    'description' => $agreement['description'],
                    'sort_order' => $agreement['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Aynı anda yayımlanan sürümler id sırasıyla ayrışır (güncel = son eklenen).
                foreach ($agreement['versions'] as $version) {
                    DB::table('dealer_agreement_versions')->insert([
                        ...$version,
                        'dealer_agreement_id' => $id,
                        'published_by' => null,
                        'published_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('dealer_agreements')
            ->whereIn('key', ['framework', 'declaration', 'marketing', 'shipping_terms', 'pre_information', 'distance_sales'])
            ->delete();
    }
};
