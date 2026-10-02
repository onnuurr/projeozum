<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yeniden onay (bayi girişinde zorunlu adım):
 * - requires_acceptance: bu sürüm yayımlandığında mevcut bayilerden (sürümden önce bayi olmuş, henüz onaylamamış)
 *   bayi portalına girişte onay istenir.
 * - variables_snapshot: sürüme dondurulmuş etiket değerleri (ör. ek sözleşmedeki gönderim yapılmayan ülkeler) —
 *   bu sürüm her zaman yayımlandığı andaki listeyle çizilir.
 * - "Gönderim Kısıtlaması Ek Sözleşmesi" (shipping_restrictions): ülke listesi değişince yeni sürümü otomatik
 *   yayımlanır (requires_acceptance + snapshot). İlk sürüm şablondur (onay istemez); metin yönetim ekranından düzenlenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->boolean('requires_acceptance')->default(false)->after('format');
            $table->json('variables_snapshot')->nullable()->after('requires_acceptance');
        });

        if (DB::table('dealer_agreements')->where('key', 'shipping_restrictions')->exists()) {
            return;
        }

        $now = now();
        $id = DB::table('dealer_agreements')->insertGetId([
            'key' => 'shipping_restrictions',
            'title' => 'Gönderim Kısıtlaması Ek Sözleşmesi',
            'display' => 'document',
            'description' => 'Gönderim yapılmayan ülkeler listesi değiştiğinde yeni sürümü otomatik yayımlanır ve mevcut bayilere portala girişte onaylatılır.',
            'sort_order' => 7,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $templates = [
            'tr' => ['Gönderim Kısıtlaması Ek Sözleşmesi', implode('', [
                '<p>İşbu ek sözleşme, <strong>{{seller.legal_name}}</strong> ("Tedarikçi") ile <strong>{{company.legal_name}}</strong> ("Bayi") arasındaki Dropshipping Bayilik Sözleşmesi\'nin ("Sözleşme") ayrılmaz parçasıdır.</p>',
                '<p><strong>1.</strong> Sözleşme\'nin 19.1. maddesinde anılan ve Ürünlerin doğrudan veya dolaylı olarak satılamayacağı, transit ettirilemeyeceği ve yeniden ihraç edilemeyeceği ülke ve bölgeler {{shipping.updated_at}} tarihi itibarıyla şunlardır: <strong>{{shipping.blocked_countries}}</strong>.</p>',
                '<p><strong>2.</strong> Bayi, Platform\'un bu ülkelere teslimat adresi ve sipariş kabul etmeyeceğini; listenin yaptırım ve ihracat mevzuatındaki değişikliklere göre Tedarikçi tarafından güncellenebileceğini ve her güncellemenin Platform üzerinden onayına sunulacağını kabul eder.</p>',
                '<p><strong>3.</strong> Sözleşme\'nin diğer hükümleri aynen geçerlidir.</p>',
                '<p>Onay tarihi: {{agreement.date}} · Sürüm: {{agreement.version}}</p>',
            ])],
            'en' => ['Shipping Restrictions Addendum', implode('', [
                '<p>This addendum forms an integral part of the Dropshipping Dealer Agreement (the "Agreement") between <strong>{{seller.legal_name}}</strong> (the "Supplier") and <strong>{{company.legal_name}}</strong> (the "Dealer").</p>',
                '<p><strong>1.</strong> As of {{shipping.updated_at}}, the countries and regions referred to in Article 19.1 of the Agreement, to which the Products may not be sold, transited or re-exported directly or indirectly, are: <strong>{{shipping.blocked_countries}}</strong>.</p>',
                '<p><strong>2.</strong> The Dealer acknowledges that the Platform will not accept delivery addresses or orders for these countries, that the Supplier may update the list in line with changes in sanctions and export control law, and that each update will be submitted for its acceptance on the Platform.</p>',
                '<p><strong>3.</strong> All other provisions of the Agreement remain unchanged.</p>',
                '<p>Accepted on: {{agreement.date}} · Version: {{agreement.version}}</p>',
            ])],
        ];

        foreach ($templates as $locale => [$title, $body]) {
            DB::table('dealer_agreement_versions')->insert([
                'dealer_agreement_id' => $id,
                'locale' => $locale,
                'version' => $now->year.'/v1',
                'title' => $title,
                'body' => $body,
                'format' => 'html',
                'requires_acceptance' => false,
                'variables_snapshot' => null,
                'change_note' => 'Şablon (ülke listesi değişince yeni sürüm otomatik yayımlanır).',
                'published_by' => null,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dealer_agreements')->where('key', 'shipping_restrictions')->delete();

        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->dropColumn(['requires_acceptance', 'variables_snapshot']);
        });
    }
};
