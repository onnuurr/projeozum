<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Models\DealerAgreement;
use Modules\Bagisto\Models\DealerAgreementAcceptance;
use Modules\Bagisto\Services\DealerAgreementService;
use Modules\Tenant\Models\TenantApplication;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bayi Sözleşmeleri: sürümlü yayın, Bagisto API'si ve başvuru API'sinin onaylanan sürümü doğrulaması.
 * Sözleşme kayıtları seed migration'ından gelir (2026_10_02_100100_seed_dealer_agreements).
 */
class DealerAgreementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bagisto.inbound.token' => 'test-token']);
        Queue::fake();
        Notification::fake();
        Mail::fake();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        foreach (['dealer-agreement.manage', 'tenant-application.approve'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->givePermissionTo(['dealer-agreement.manage', 'tenant-application.approve']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function framework(): DealerAgreement
    {
        return DealerAgreement::where('key', 'framework')->firstOrFail();
    }

    public function test_seed_moves_portal_texts_and_fixes_article_4(): void
    {
        $versions = $this->framework()->versions()->pluck('version')->all();

        $this->assertSame(['2026/v1', '2025/v2.1'], $versions);
        $this->assertStringContainsString('kredi kartına', $this->framework()->currentVersion->body);
        $this->assertStringNotContainsString('mahsup', $this->framework()->currentVersion->body);
        $this->assertNull(DealerAgreement::where('key', 'distance_sales')->firstOrFail()->currentVersion);
    }

    public function test_index_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/dealer-agreements')->assertForbidden();

        $this->withoutVite();
        $this->actingAs($this->admin)->get('/dealer-agreements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Bagisto::DealerAgreements', false)->has('agreements', 7));
    }

    public function test_publish_creates_next_version_and_keeps_history(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2));
        $framework = $this->framework();

        $this->actingAs($this->admin)
            ->post("/dealer-agreements/{$framework->id}/publish", [
                'title' => 'Ozm Kids B2B Bayilik Çerçeve Sözleşmesi',
                'body' => '<p><strong>Madde 1:</strong> Yeni metin.</p><script>alert(1)</script><p style="text-align: center; color: red" onclick="x()">Madde 2.</p>',
                'change_note' => 'Madde 1 sadeleşti',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $current = $framework->fresh()->currentVersion;
        $this->assertSame('2026/v2', $current->version);
        $this->assertSame($this->admin->id, $current->published_by);
        $this->assertSame(3, $framework->versions()->count());

        // Zengin metin HTML'i süzülür: izinli biçimler (kalın, hizalama) kalır; script, renk, olay öznitelikleri atılır.
        $this->assertSame('html', $current->format);
        $html = app(DealerAgreementService::class)->feed()['framework']['html'];
        $this->assertStringContainsString('<strong>Madde 1:</strong>', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('color', $html);
    }

    public function test_publishing_identical_text_is_rejected(): void
    {
        $framework = $this->framework();
        $body = '<p><strong>Madde 1:</strong> Metin.</p>';

        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => $body])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => $body."\n"])
            ->assertSessionHasErrors('body');

        // Boş editör (yalnızca boş paragraf) yayımlanamaz.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => '<p></p>'])
            ->assertSessionHasErrors('body');

        $this->assertSame(3, $framework->versions()->count());
    }

    public function test_legacy_markdown_versions_still_render_and_open_in_the_editor_as_html(): void
    {
        $service = app(DealerAgreementService::class);
        $legacy = $this->framework()->currentVersion;

        $this->assertSame('markdown', $legacy->format);
        $this->assertStringContainsString('<strong>Madde 4 – İade:</strong>', $service->feed()['framework']['html']);
        $this->assertStringStartsWith('<p><strong>Madde 1', $service->editorHtml($legacy->body, $legacy->format));
    }

    public function test_publish_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/dealer-agreements/{$this->framework()->id}/publish", ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();
    }

    public function test_api_feed_requires_token_and_returns_current_versions(): void
    {
        $this->getJson('/api/bagisto/dealer-agreements')->assertUnauthorized();

        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements')
            ->assertOk()
            ->assertJsonPath('data.framework.version', '2026/v1')
            ->assertJsonPath('data.framework.display', 'document')
            ->assertJsonPath('data.declaration.display', 'consent')
            ->assertJsonPath('data.pre_information.version', null)
            ->assertJsonPath('data.pre_information.html', null);
    }

    public function test_application_rejects_unknown_declaration_version_and_stores_valid_ones(): void
    {
        $payload = [
            'name' => 'Lila Butik', 'tax_number' => '1234567899', 'tax_office' => 'Kadıköy',
            'contact_person' => 'Buse Karaca', 'contact_email' => 'lila@butik.test', 'contact_phone' => '0532 840 21 00',
            'address' => 'Bağdat Cad. 142',
            'details' => [
                'company_type' => 'limited', 'dealer_model' => 'dropshipping',
                'channels' => [['type' => 'instagram', 'value' => '@lila']], 'monthly_volume' => '150-500',
                'consents' => ['declaration' => '1', 'declaration_version' => '1999/v9', 'marketing' => '1', 'marketing_version' => '2026/v1'],
            ],
        ];

        $this->withToken('test-token')->postJson('/api/bagisto/tenant-applications', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('details.consents.declaration_version');

        $payload['details']['consents']['declaration_version'] = '2026/v1';
        $token = $this->withToken('test-token')->postJson('/api/bagisto/tenant-applications', $payload)
            ->assertCreated()
            ->json('data.token');

        $consents = TenantApplication::where('public_token', $token)->firstOrFail()->details['consents'];
        $this->assertSame('2026/v1', $consents['declaration_version']);
        $this->assertSame('2026/v1', $consents['marketing_version']);
    }

    public function test_publish_rejects_unknown_tags_and_preview_fills_sample_data(): void
    {
        $framework = $this->framework();

        $this->actingAs($this->admin)
            ->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Sözleşme', 'body' => '{{company.legal_name}} ile {{firma.unvani}} arasında.'])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->admin)
            ->postJson('/dealer-agreements/preview', ['body' => 'İşbu sözleşme {{company.legal_name}} ({{company.tax_number}}) ile {{x.y}}.', 'display' => 'document'])
            ->assertOk()
            ->assertJsonPath('unknown', ['x.y'])
            ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Lila Bebek ve Çocuk Giyim Ltd. Şti.') && str_contains($html, '1234567899'))->etc());
    }

    public function test_official_step_shows_filled_contract_and_archives_acceptance(): void
    {
        $framework = $this->framework();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", [
            'title' => 'Çerçeve Sözleşme',
            'body' => '**Madde 1:** İşbu sözleşme Ozm Kids ile {{company.legal_name}} (VKN {{company.tax_number}}) arasında {{agreement.date}} tarihinde yapılmıştır.',
        ])->assertSessionHasNoErrors();

        $application = TenantApplication::create([
            'name' => 'Lila Butik', 'legal_name' => 'Lila Bebek Ltd. Şti.', 'tax_number' => '1234567899', 'tax_office' => 'Kadıköy',
            'contact_person' => 'Buse Karaca', 'contact_email' => 'lila@butik.test', 'contact_phone' => '0532 840 21 00',
            'address' => 'Bağdat Cad. 142', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'country' => 'TR',
            'source' => TenantApplication::SOURCE_BAGISTO, 'status' => TenantApplication::STATUS_DOCUMENTS_REQUESTED,
            'public_token' => str_repeat('a', 48), 'details' => [],
        ]);

        // Durum sayfası: sözleşme bayinin bilgileriyle doldurulmuş gelir.
        $html = $this->withToken('test-token')->getJson('/api/bagisto/tenant-applications/'.str_repeat('a', 48))
            ->assertOk()
            ->json('data.agreements.framework.html');
        $this->assertStringContainsString('Lila Bebek Ltd. Şti.', $html);
        $this->assertStringContainsString('1234567899', $html);

        $this->withToken('test-token')->post('/api/bagisto/tenant-applications/'.str_repeat('a', 48).'/official', [
            'ip_address' => '203.0.113.7',
            'details' => [
                'revenue_target' => '100-250', 'payment_preference' => 'kart',
                'bank' => ['bank_name' => 'Garanti', 'account_holder' => 'Lila', 'iban' => 'TR540006200011223344556677'],
                'consents' => ['contract' => '1', 'contract_version' => '2026/v2'],
            ],
            'vergi_levhasi' => \Illuminate\Http\UploadedFile::fake()->create('vergi.pdf', 50, 'application/pdf'),
            'imza_sirkuleri' => \Illuminate\Http\UploadedFile::fake()->create('imza.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $acceptance = DealerAgreementAcceptance::where('tenant_application_id', $application->id)->sole();
        $this->assertSame('framework', $acceptance->agreement_key);
        $this->assertSame('2026/v2', $acceptance->version);
        $this->assertSame(DealerAgreementAcceptance::CONTEXT_OFFICIAL, $acceptance->context);
        $this->assertSame('203.0.113.7', $acceptance->ip_address);
        $this->assertStringContainsString('Lila Bebek Ltd. Şti.', $acceptance->html);
        $this->assertStringContainsString(now()->format('d.m.Y'), $acceptance->html);

        // Başvuru detayı listesi + yazdırılabilir kopya (başvuru yetkisiyle).
        $this->actingAs($this->admin)->getJson('/dealer-agreements/acceptances?application='.$application->id)
            ->assertOk()
            ->assertJsonPath('data.0.version', '2026/v2')
            ->assertJsonPath('data.0.context', 'Resmi belge adımı');

        $this->actingAs($this->admin)->get("/dealer-agreements/acceptances/{$acceptance->id}/print")
            ->assertOk()
            ->assertSee('Lila Bebek Ltd. Şti.')
            ->assertSee('203.0.113.7');

        $this->actingAs(User::factory()->create())->get("/dealer-agreements/acceptances/{$acceptance->id}/print")->assertForbidden();
    }

    public function test_seller_and_custom_tags_are_saved_and_rendered(): void
    {
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', [
            'seller' => ['legal_name' => 'Ozm Kids Tekstil Ltd. Şti.', 'mersis' => '0123456789000015'],
            'custom_tags' => [['key' => 'iade_suresi', 'description' => 'İade süresi', 'value' => '14 gün']],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $framework = $this->framework();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", [
            'title' => 'Çerçeve Sözleşme',
            'body' => '{{seller.legal_name}} (MERSİS {{seller.mersis}}) ile {{company.legal_name}} arasında; iade süresi {{custom.iade_suresi}}.',
        ])->assertSessionHasNoErrors();

        // Bayiden bağımsız ekranlarda (genel akış) firma bilgisi ve özel etiket dolu, bayi etiketi boş çizgi.
        $html = app(DealerAgreementService::class)->feed()['framework']['html'];
        $this->assertStringContainsString('Ozm Kids Tekstil Ltd. Şti.', $html);
        $this->assertStringContainsString('0123456789000015', $html);
        $this->assertStringContainsString('14 gün', $html);
        $this->assertStringContainsString('____', $html);

        // Güncel sürümde kullanılan özel etiket silinemez.
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['seller' => [], 'custom_tags' => []])
            ->assertSessionHasErrors('custom_tags');

        // Tamamen boş bırakılan satır (Etiket ekle → doldurulmadı) formun geri kalanını reddettirmez.
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', [
            'seller' => ['legal_name' => 'Ozm Kids Tekstil Ltd. Şti.', 'name' => null],
            'custom_tags' => [['key' => 'iade_suresi', 'description' => 'İade süresi', 'value' => '14 gün'], ['key' => '', 'description' => '', 'value' => '']],
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, app(DealerAgreementService::class)->customTags());

        // Geçersiz / tekrarlı anahtar reddedilir.
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['custom_tags' => [['key' => 'İade Süresi']]])
            ->assertSessionHasErrors('custom_tags.0.key');
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['custom_tags' => [['key' => 'iade_suresi'], ['key' => 'iade_suresi']]])
            ->assertSessionHasErrors('custom_tags');
    }

    public function test_english_versions_are_separate_and_not_served_to_the_portal_by_default(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2));
        $framework = $this->framework();

        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", [
            'locale' => 'en',
            'title' => 'Ozm Kids B2B Dealer Framework Agreement',
            'body' => 'This agreement is made between {{seller.legal_name}} and {{company.legal_name}} ({{company.type}}).',
        ])->assertSessionHasNoErrors();

        // İngilizce kendi sürüm serisini başlatır; Türkçe güncel sürüm değişmez.
        $english = $framework->versions()->where('locale', 'en')->sole();
        $this->assertSame('2026/v1', $english->version);
        $this->assertSame('2026/v1', $framework->fresh()->currentVersion->version);
        $this->assertSame('tr', $framework->fresh()->currentVersion->locale);

        // Portal varsayılanı Türkçe; İngilizce yalnızca ?locale=en ile.
        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements')
            ->assertJsonPath('locale', 'tr')
            ->assertJsonPath('data.framework.title', 'Ozm Kids B2B Bayilik Çerçeve Sözleşmesi');
        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements?locale=en')
            ->assertJsonPath('data.framework.title', 'Ozm Kids B2B Dealer Framework Agreement')
            ->assertJsonPath('data.framework.version', '2026/v1')
            ->assertJsonPath('data.declaration.version', null);

        // İngilizce sürüm numarası Türkçe onay için geçerli sayılmaz.
        $service = app(DealerAgreementService::class);
        $this->assertTrue($service->isPublishedVersion('framework', '2026/v1', 'en'));
        $this->assertFalse($service->isPublishedVersion('framework', '2025/v2.1', 'en'));

        // Önizleme İngilizce seçenek etiketleriyle doldurulur.
        $this->actingAs($this->admin)
            ->postJson('/dealer-agreements/preview', ['body' => '{{company.type}}', 'display' => 'consent', 'locale' => 'en'])
            ->assertJsonPath('html', 'Limited company');
    }

    public function test_drafts_are_saved_per_locale_not_served_and_cleared_on_publish(): void
    {
        $framework = $this->framework();
        $published = $framework->currentVersion->body;

        $this->actingAs($this->admin)->put("/dealer-agreements/{$framework->id}/draft", [
            'locale' => 'tr', 'title' => 'Taslak başlık', 'body' => 'Yarım kalan {{bilinmeyen}} metin', 'change_note' => 'not',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        // Üzerine yazılır (sözleşme + dil başına tek taslak); İngilizce taslak ayrı.
        $this->actingAs($this->admin)->put("/dealer-agreements/{$framework->id}/draft", ['locale' => 'tr', 'title' => 'Taslak başlık', 'body' => 'İkinci hâl'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put("/dealer-agreements/{$framework->id}/draft", ['locale' => 'en', 'title' => 'Draft', 'body' => 'English draft'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $framework->drafts()->count());
        $this->assertSame('İkinci hâl', $framework->drafts()->where('locale', 'tr')->value('body'));

        // Bayi portalı yayımlanmış sürümü görmeye devam eder.
        $this->assertStringContainsString('kredi kartına', app(DealerAgreementService::class)->feed()['framework']['html']);
        $this->assertSame($published, $framework->fresh()->currentVersion->body);

        $this->withoutVite();
        $this->actingAs($this->admin)->get('/dealer-agreements')
            ->assertInertia(fn ($page) => $page->component('Bagisto::DealerAgreements', false)
                ->where('agreements.0.drafts.tr.body', 'İkinci hâl')
                ->where('agreements.0.drafts.en.title', 'Draft'));

        // Yayımlanınca yalnızca o dilin taslağı silinir.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => 'İkinci hâl'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['en'], $framework->drafts()->pluck('locale')->all());

        // Taslağı sil.
        $this->actingAs($this->admin)->delete("/dealer-agreements/{$framework->id}/draft", ['locale' => 'en'])->assertSessionHas('success');
        $this->assertSame(0, $framework->drafts()->count());

        // Yetkisiz kullanıcı taslak kaydedemez.
        $this->actingAs(User::factory()->create())->put("/dealer-agreements/{$framework->id}/draft", ['locale' => 'tr', 'body' => 'x'])->assertForbidden();
    }

    public function test_annexes_are_versioned_independently_referenced_rendered_and_archived(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2));
        $framework = $this->framework();

        // Ek aç (Ek-1) ve metnini tabloyla yayımla.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/annexes", ['title' => 'İskonto Tablosu'])
            ->assertSessionHasNoErrors();
        $annex = DealerAgreement::where('parent_id', $framework->id)->sole();
        $this->assertSame(1, $annex->annex_number);
        $this->assertSame('framework-ek1', $annex->key);

        $table = '<table><tbody><tr><th><p>Kademe</p></th><th><p>İskonto</p></th></tr><tr><td><p>{{company.legal_name}}</p></td><td><p>%{{custom.iskonto}}</p></td></tr></tbody></table>';
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['custom_tags' => [['key' => 'iskonto', 'value' => '30']]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$annex->id}/publish", ['title' => 'İskonto Tablosu', 'body' => $table])
            ->assertSessionHasNoErrors();

        // Ana metin: tanımsız ek numarası reddedilir, {{annex.1}} kabul edilir.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => '<p>Bkz. {{annex.2}}</p>'])
            ->assertSessionHasErrors('body');
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => '<p>İskontolar {{annex.1}} uyarınca uygulanır.</p>'])
            ->assertSessionHasNoErrors();

        // Ek bağımsız sürümlenir: ekte ikinci yayın ana metnin sürümünü değiştirmez.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$annex->id}/publish", ['title' => 'İskonto Tablosu', 'body' => $table.'<p>Güncellendi.</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026/v2', $annex->fresh()->currentVersion->version);
        $this->assertSame('2026/v2', $framework->fresh()->currentVersion->version);

        // Feed: ana metinde ek bağlantısı, ekler listesi (tablo korunur, etiketler dolu).
        $feed = app(DealerAgreementService::class)->feed()['framework'];
        $this->assertStringContainsString('<a href="#ek-1">Ek-1: İskonto Tablosu</a>', $feed['html']);
        $this->assertSame('framework-ek1', $feed['annexes'][0]['key']);
        $this->assertSame('2026/v2', $feed['annexes'][0]['version']);
        $this->assertStringContainsString('<table>', $feed['annexes'][0]['html']);
        $this->assertStringContainsString('%30', $feed['annexes'][0]['html']);

        // Resmi belge adımı: bayinin gördüğü ek sürümü (eski v1) doğrulanır ve kopyaya yazılır.
        $application = TenantApplication::create([
            'name' => 'Lila Butik', 'legal_name' => 'Lila Bebek Ltd. Şti.', 'tax_number' => '1234567899', 'tax_office' => 'Kadıköy',
            'contact_person' => 'Buse Karaca', 'contact_email' => 'lila@butik.test', 'contact_phone' => '0532 840 21 00',
            'address' => 'Bağdat Cad. 142', 'country' => 'TR', 'source' => TenantApplication::SOURCE_BAGISTO,
            'status' => TenantApplication::STATUS_DOCUMENTS_REQUESTED, 'public_token' => str_repeat('b', 48), 'details' => [],
        ]);
        $official = fn (array $annexes) => $this->withToken('test-token')->post('/api/bagisto/tenant-applications/'.str_repeat('b', 48).'/official', [
            'details' => [
                'revenue_target' => '100-250', 'payment_preference' => 'kart',
                'bank' => ['bank_name' => 'Garanti', 'account_holder' => 'Lila', 'iban' => 'TR540006200011223344556677'],
                'consents' => ['contract' => '1', 'contract_version' => '2026/v2', 'contract_annexes' => $annexes],
            ],
            'vergi_levhasi' => \Illuminate\Http\UploadedFile::fake()->create('vergi.pdf', 50, 'application/pdf'),
            'imza_sirkuleri' => \Illuminate\Http\UploadedFile::fake()->create('imza.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $official(['framework-ek1' => '2099/v9'])->assertUnprocessable();
        $official(['declaration' => '2026/v1'])->assertUnprocessable();
        $official(['framework-ek1' => '2026/v1'])->assertOk();

        $acceptance = DealerAgreementAcceptance::where('tenant_application_id', $application->id)->sole();
        $this->assertSame([['key' => 'framework-ek1', 'number' => 1, 'title' => 'İskonto Tablosu', 'version' => '2026/v1']], $acceptance->data['annexes']);
        $this->assertStringContainsString('id="ek-1"', $acceptance->html);
        $this->assertStringContainsString('Lila Bebek Ltd. Şti.', $acceptance->html);
        $this->assertStringNotContainsString('Güncellendi.', $acceptance->html); // bayi v1'i gördü
        $this->assertSame(['framework-ek1' => '2026/v1'], $application->fresh()->details['consents']['contract_annex_versions']);

        // Ana metinde anılan ek kaldırılamaz; atıf kaldırılınca kaldırılır ve feed'den düşer.
        $this->actingAs($this->admin)->delete("/dealer-agreements/{$annex->id}/annex")->assertSessionHasErrors('annex');
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", ['title' => 'Çerçeve', 'body' => '<p>Ekler kaldırıldı.</p>'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/dealer-agreements/{$annex->id}/annex")->assertSessionHasNoErrors();
        $this->assertSame([], app(DealerAgreementService::class)->feed()['framework']['annexes']);

        // Numara yeniden kullanılmaz; onay kutusu metnine ek açılamaz.
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/annexes", ['title' => 'Yeni ek'])->assertSessionHasNoErrors();
        $this->assertSame(2, DealerAgreement::where('parent_id', $framework->id)->max('annex_number'));
        $declaration = DealerAgreement::where('key', 'declaration')->sole();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$declaration->id}/annexes", ['title' => 'X'])->assertSessionHasErrors('title');
    }

    public function test_blocked_countries_fill_the_contract_tag_per_language_and_reach_the_portal(): void
    {
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', [
            'blocked_countries' => ['KP', 'IR', 'SY'],
            'blocked_regions' => ['tr' => 'Kırım, Donetsk', 'en' => 'Crimea, Donetsk'],
        ])->assertSessionHasNoErrors();

        $service = app(DealerAgreementService::class);
        $this->assertSame(['KP', 'IR', 'SY'], $service->blockedCountries());
        $this->assertSame('İran, Kuzey Kore, Suriye, Kırım, Donetsk', $service->blockedCountriesText('tr'));
        $this->assertSame('Iran, North Korea, Syria, Crimea, Donetsk', $service->blockedCountriesText('en'));

        // Sözleşmede etiket (dilinde) — bayiden bağımsız, genel akışta da dolu.
        $framework = $this->framework();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", [
            'title' => 'Çerçeve', 'body' => '<p>Ürünleri yaptırım uygulanan ülke veya bölgelere (özellikle {{shipping.blocked_countries}}) satmayacaktır.</p>',
        ])->assertSessionHasNoErrors();
        $this->assertStringContainsString('özellikle İran, Kuzey Kore, Suriye, Kırım, Donetsk', $service->feed()['framework']['html']);

        // Mağaza engeli için kodlar API'de.
        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements')
            ->assertJsonPath('blocked_countries', ['KP', 'IR', 'SY']);

        // Geçersiz kod reddedilir.
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['blocked_countries' => ['XX']])
            ->assertSessionHasErrors('blocked_countries.0');
    }

    public function test_country_change_publishes_addendum_that_existing_dealers_must_accept_on_login(): void
    {
        $tenant = \Modules\Tenant\Models\Tenant::factory()->create(['name' => 'Lila Butik', 'legal_name' => 'Lila Bebek Ltd. Şti.', 'tax_number' => '1234567899']);
        $dealer = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'bayi@lila.test']);
        $api = fn () => $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements/pending?email=bayi@lila.test');

        // Henüz yeniden onay gerektiren yayın yok.
        $api()->assertOk()->assertExactJson(['data' => []]);

        // Ülke listesi değişince ek sözleşme yeni sürümü otomatik yayımlanır (liste sürüme dondurulur).
        $this->travel(1)->seconds();
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['blocked_countries' => ['IR', 'KP']])
            ->assertSessionHasNoErrors();
        $addendum = DealerAgreement::where('key', 'shipping_restrictions')->sole();
        $v2 = $addendum->currentVersion;
        $this->assertTrue($v2->requires_acceptance);
        $this->assertSame('İran, Kuzey Kore', $v2->variables_snapshot['shipping.blocked_countries']);

        // Liste kaydedilip değişmeyince yeni sürüm açılmaz.
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['blocked_countries' => ['KP', 'IR']])->assertSessionHasNoErrors();
        $this->assertSame($v2->id, $addendum->fresh()->currentVersion->id);

        // Bayiden onay bekler; metin bayinin ve dondurulmuş listenin bilgileriyle dolu.
        $pending = $api()->assertOk()->json('data');
        $this->assertSame(['shipping_restrictions'], array_keys($pending));
        $this->assertStringContainsString('Lila Bebek Ltd. Şti.', $pending['shipping_restrictions']['html']);
        $this->assertStringContainsString('İran, Kuzey Kore', $pending['shipping_restrictions']['html']);

        // Eski / yanlış sürüm reddedilir; doğru sürüm onaylanınca bekleyen kalmaz, arşive yazılır.
        $this->withToken('test-token')->postJson('/api/bagisto/dealer-agreements/accept', ['email' => 'bayi@lila.test', 'accepted' => ['shipping_restrictions' => '1999/v1']])
            ->assertUnprocessable();
        $this->withToken('test-token')->postJson('/api/bagisto/dealer-agreements/accept', [
            'email' => 'bayi@lila.test', 'accepted' => ['shipping_restrictions' => $v2->version], 'ip_address' => '203.0.113.9',
        ])->assertOk()->assertExactJson(['data' => []]);
        $acceptance = DealerAgreementAcceptance::where('tenant_id', $tenant->id)->sole();
        $this->assertSame(DealerAgreementAcceptance::CONTEXT_RENEWAL, $acceptance->context);
        $this->assertStringContainsString('İran, Kuzey Kore', $acceptance->html);

        // Liste yeniden değişince yine onay ister; eski sürüm kendi (dondurulmuş) listesiyle kalır.
        $this->travel(1)->seconds();
        $this->actingAs($this->admin)->put('/dealer-agreements/settings', ['blocked_countries' => ['IR', 'KP', 'SY']])->assertSessionHasNoErrors();
        $this->assertSame(['shipping_restrictions'], array_keys($api()->json('data')));
        $this->assertSame('İran, Kuzey Kore', $v2->fresh()->variables_snapshot['shipping.blocked_countries']);

        // Değişiklikten sonra çerçeve sözleşmeyi onaylamış (yeni) bayiden ek sözleşme istenmez.
        $this->travel(1)->seconds();
        $newTenant = \Modules\Tenant\Models\Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $newTenant->id, 'email' => 'yeni@bayi.test']);
        app(DealerAgreementService::class)->recordTenantAcceptance($newTenant, 'framework', $this->framework()->currentVersion->version, DealerAgreementAcceptance::CONTEXT_MEMBERSHIP);
        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements/pending?email=yeni@bayi.test')->assertExactJson(['data' => []]);

        // "Yeniden onay iste" ile yayımlanan çerçeve sürümü tüm mevcut bayilere girişte sorulur.
        $this->travel(1)->seconds();
        $framework = $this->framework();
        $this->actingAs($this->admin)->post("/dealer-agreements/{$framework->id}/publish", [
            'title' => 'Çerçeve', 'body' => '<p>Yeni çerçeve metni.</p>', 'requires_acceptance' => true,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(['framework', 'shipping_restrictions'], array_keys($api()->json('data')));
        $this->assertSame(['framework'], array_keys($this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements/pending?email=yeni@bayi.test')->json('data')));

        // Bilinmeyen bayi: boş liste.
        $this->withToken('test-token')->getJson('/api/bagisto/dealer-agreements/pending?email=yok@yok.test')->assertExactJson(['data' => []]);
    }
}

