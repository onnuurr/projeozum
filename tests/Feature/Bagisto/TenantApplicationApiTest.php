<?php

namespace Tests\Feature\Bagisto;

use App\Contracts\PlatformSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Bagisto\Jobs\PushStorefrontFeaturesToBagisto;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Bagisto\Mail\DealerApplicationMail;
use Modules\Bagisto\Services\StorefrontFeatureService;
use Modules\Superadmin\Models\Setting;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Notifications\TenantApplicationActivityNotification;
use Modules\Tenant\Services\TenantService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bagisto bayi portalı (ozmbayi) → SaaS bayilik başvurusu köprüsü, iki aşamalı akış:
 * ön başvuru → (admin) resmi belge aşaması → belgeler → evrak incelemesi (⇄ eksik evrak) →
 * (admin) hesap açılışı → bayinin üyelik tamamlaması.
 */
class TenantApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bagisto.inbound.token' => 'test-token']);
        Storage::fake('local');
        Queue::fake();
        Notification::fake();
        Mail::fake();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        foreach (['tenant-application.approve', 'tenant.manage', 'tenant.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->givePermissionTo(['tenant-application.approve', 'tenant.manage', 'tenant.view']);
        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
    }

    private function api(): static
    {
        return $this->withHeader('Authorization', 'Bearer test-token')->withHeader('Accept', 'application/json');
    }

    private function preApplication(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Lila Bebek & Çocuk Butik',
            'legal_name' => 'Lila Bebek & Çocuk Butik Ltd. Şti.',
            'tax_number' => '1234567899',
            'tax_office' => 'Kadıköy',
            'contact_person' => 'Buse Karaca',
            'contact_email' => 'siparis@lilabutik.test',
            'contact_phone' => '0532 840 21 00',
            'address' => 'Caddebostan Mah. Bağdat Cad. No:142',
            'city' => 'İstanbul',
            'district' => 'Kadıköy',
            'details' => [
                'company_type' => 'limited',
                'dealer_model' => 'dropshipping',
                'channels' => [['type' => 'instagram', 'value' => '@lilacocukbutik'], ['type' => 'pazaryeri', 'value' => 'Trendyol 391829']],
                'monthly_volume' => '150-500',
                'consents' => ['declaration' => '1', 'marketing' => '0'],
            ],
        ], $overrides);
    }

    private function officialStep(array $overrides = []): array
    {
        return array_replace_recursive([
            'message' => 'Kadıköy mağazamız için.',
            'details' => [
                'revenue_target' => '100-250',
                'payment_preference' => 'kart',
                'bank' => ['bank_name' => 'Garanti BBVA', 'account_holder' => 'Lila Ltd.', 'iban' => 'TR540006200011223344556677'],
                'consents' => ['contract' => '1', 'contract_version' => '2025/v2.1'],
            ],
            'vergi_levhasi' => UploadedFile::fake()->create('vergi.pdf', 200, 'application/pdf'),
            'imza_sirkuleri' => UploadedFile::fake()->create('imza.pdf', 200, 'application/pdf'),
        ], $overrides);
    }

    private function completion(array $overrides = []): array
    {
        return array_merge([
            'contact_person' => 'Buse Karaca Yılmaz',
            'contact_phone' => '0532 840 21 01',
            'address' => 'Caddebostan Mah. Bağdat Cad. No:142',
            'city' => 'İstanbul',
            'district' => 'Kadıköy',
            'postal_code' => '34728',
            'shipping_agreement_type' => 'platform',
            'shipping_terms_accepted' => true,
            'contract' => true,
            'contract_version' => '2025/v2.1',
        ], $overrides);
    }

    /** Ön başvuru → belge aşaması açık → resmi belgeler yüklenmiş (documents_review). */
    private function applicationInReview(): TenantApplication
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->firstOrFail();

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents")->assertRedirect();
        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep())->assertOk();

        return $application->fresh();
    }

    private function openAccount(TenantApplication $application): TenantApplication
    {
        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/open-account", ['code' => 'BAYI-0001', 'credit_limit' => 50000])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return $application->fresh();
    }

    public function test_requires_bagisto_token(): void
    {
        $this->postJson('/api/bagisto/tenant-applications', [])->assertStatus(401);
    }

    public function test_pre_application_is_queued_without_documents_and_notifies_reviewers(): void
    {
        $response = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.details.dealer_model', 'dropshipping')
            ->assertJsonPath('data.details.consents.declaration', true)
            ->assertJsonCount(0, 'data.documents');

        $application = TenantApplication::first();

        $this->assertSame('bagisto', $application->source);
        $this->assertSame(48, strlen($application->public_token));
        $this->assertArrayNotHasKey('bank', $application->details);

        Notification::assertSentTo($this->staff, TenantApplicationActivityNotification::class);
        Mail::assertQueued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'received' && $mail->hasTo('siparis@lilabutik.test'));
    }

    public function test_pre_application_requires_declaration(): void
    {
        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication([
            'details' => ['consents' => ['declaration' => '0']],
        ]))->assertStatus(422)->assertJsonValidationErrors(['details.consents.declaration']);
    }

    public function test_pre_application_is_refused_when_signup_is_closed(): void
    {
        Setting::setGroup('general', ['allowSignup' => false]);

        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())
            ->assertStatus(403)
            ->assertJsonPath('code', 'applications_closed');

        $this->assertSame(0, TenantApplication::count());
    }

    public function test_signup_gate_reads_platform_settings_contract_not_superadmin(): void
    {
        $this->app->instance(PlatformSettings::class, new class implements PlatformSettings
        {
            public function group(string $group): array
            {
                return [];
            }

            public function putGroup(string $group, array $values): void {}

            public function allowsSignup(): bool
            {
                return false;
            }
        });

        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->assertStatus(403);
        $this->assertFalse(app(StorefrontFeatureService::class)->values()['dealer_applications']);
    }

    public function test_ongoing_application_continues_when_signup_is_closed(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->assertCreated()->json('data.token');

        Setting::setGroup('general', ['allowSignup' => false]);

        $this->api()->get("/api/bagisto/tenant-applications/{$token}")->assertOk();
    }

    public function test_toggling_signup_pushes_dealer_applications_switch_to_store(): void
    {
        Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
        $this->staff->givePermissionTo('settings.manage');

        $this->actingAs($this->staff)->post('/superadmin/settings', ['general' => ['allowSignup' => false]])->assertRedirect();

        Queue::assertPushed(PushStorefrontFeaturesToBagisto::class);
        $this->assertFalse(app(StorefrontFeatureService::class)->values()['dealer_applications']);

        Queue::fake();
        $this->actingAs($this->staff)->post('/superadmin/settings', ['general' => ['allowSignup' => false]])->assertRedirect();

        Queue::assertNotPushed(PushStorefrontFeaturesToBagisto::class);
    }

    public function test_official_step_is_closed_until_staff_opens_it(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');

        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep())->assertStatus(409);
    }

    public function test_opening_document_stage_mails_applicant(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents")->assertRedirect();

        $this->assertSame('documents_requested', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->documents_requested_at);
        Mail::assertQueued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'documents_requested' && $mail->hasTo('siparis@lilabutik.test'));
    }

    public function test_documents_reminder_mails_applicant_and_is_throttled(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();

        // Belge adımı açılmadan hatırlatma yok.
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/remind-documents")->assertInvalid('application');

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents");
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/remind-documents")->assertValid();

        $this->assertNotNull($application->fresh()->documents_reminded_at);
        Mail::assertQueued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'documents_reminder' && $mail->hasTo('siparis@lilabutik.test'));

        // Art arda ikinci tıklama reddedilir, süre dolunca tekrar gönderilebilir.
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/remind-documents")->assertInvalid('application');
        $this->travel(TenantApplication::REMINDER_COOLDOWN_MINUTES + 1)->minutes();
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/remind-documents")->assertValid();

        Mail::assertQueued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'documents_reminder', 2);
    }

    public function test_documents_reminder_mail_renders(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();

        $html = (new DealerApplicationMail($application, 'documents_reminder'))->render();

        $this->assertStringContainsString('Hatırlatma', $html);
        $this->assertStringContainsString('Belgeleri Yükle', $html);
    }

    public function test_bank_transfer_payment_is_refused_while_cari_account_is_off(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents");

        Setting::setGroup('bagisto_features', ['cari_account' => false]);
        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep(['details' => ['payment_preference' => 'havale']]))
            ->assertStatus(422)->assertJsonValidationErrors('details.payment_preference');

        Setting::setGroup('bagisto_features', ['cari_account' => true]);
        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep(['details' => ['payment_preference' => 'havale']]))
            ->assertSuccessful();

        $this->assertSame('havale', $application->fresh()->details['payment_preference']);
    }

    public function test_official_step_validates_consent_and_iban(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents");

        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep([
            'details' => ['bank' => ['iban' => 'TR12'], 'consents' => ['contract' => '0']],
        ]))->assertStatus(422)->assertJsonValidationErrors(['details.bank.iban', 'details.consents.contract']);
    }

    public function test_official_step_moves_application_to_document_review(): void
    {
        $application = $this->applicationInReview();

        $this->assertSame('documents_review', $application->status);
        $this->assertSame('TR540006200011223344556677', $application->details['bank']['iban']);
        $this->assertSame('dropshipping', $application->details['dealer_model'], 'ön başvuru detayları korunur');
        $this->assertTrue($application->details['consents']['declaration']);
        $this->assertTrue($application->details['consents']['contract']);
        $this->assertCount(2, $application->documents);

        $this->api()->get("/api/bagisto/tenant-applications/{$application->public_token}")
            ->assertOk()
            ->assertJsonPath('data.details.bank.iban', 'TR54 **** **** 6677');
    }

    public function test_missing_documents_round_trip_returns_to_review(): void
    {
        $application = $this->applicationInReview();
        $token = $application->public_token;

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/request-documents", [
                'types' => ['vergi_levhasi'],
                'note' => '2024/2025 tasdikli barkodlu vergi levhası yükleyin.',
            ])->assertRedirect();

        $this->api()->get("/api/bagisto/tenant-applications/{$token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'missing_documents')
            ->assertJsonPath('data.missing_documents.0.type', 'vergi_levhasi');

        $this->api()->post("/api/bagisto/tenant-applications/{$token}/documents", [
            'vergi_levhasi' => UploadedFile::fake()->create('vergi-2025.pdf', 150, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.status', 'documents_review');

        $this->assertCount(3, $application->fresh()->documents);
        $this->assertNull($application->fresh()->missing_documents);
    }

    public function test_missing_documents_mail_lists_requested_documents_and_note(): void
    {
        $application = $this->applicationInReview();

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/request-documents", [
            'types' => ['vergi_levhasi'],
            'note' => '2024/2025 tasdikli barkodlu vergi levhası yükleyin.',
        ])->assertRedirect();

        $mail = Mail::queued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'missing_documents')->first();
        $this->assertNotNull($mail);

        $html = $mail->render();
        $this->assertStringContainsString('Güncel vergi levhası', $html);
        $this->assertStringContainsString('2024/2025 tasdikli barkodlu vergi levhası yükleyin.', $html);
        $this->assertStringContainsString('/bayilik-basvurusu/durum/'.$application->public_token, $html);
    }

    public function test_rejection_mails_applicant_with_reason(): void
    {
        $application = $this->applicationInReview();

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/reject", ['rejection_reason' => 'Bölgede aktif bayimiz var.'])
            ->assertRedirect();

        $mail = Mail::queued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'rejected')->first();
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Bölgede aktif bayimiz var.', $mail->render());
    }

    public function test_disabled_stage_sends_no_mail(): void
    {
        Setting::setGroup('dealer_mail', ['received' => ['enabled' => false, 'subject' => 'x', 'intro' => 'y', 'body' => '']]);

        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->assertCreated();

        Mail::assertNotQueued(DealerApplicationMail::class);
    }

    public function test_saved_texts_are_used_with_tags_filled(): void
    {
        Setting::setGroup('dealer_mail', ['received' => [
            'enabled' => true,
            'subject' => '{{company.name}} başvurusu alındı',
            'intro' => 'Merhaba {{ user.name }}, referansınız {{application.reference}}.',
            'body' => '{{user.email}} adresine yazacağız.',
        ]]);

        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->assertCreated();
        $application = TenantApplication::first();

        $mail = Mail::queued(DealerApplicationMail::class)->first();
        $html = $mail->render();

        $this->assertSame('Lila Bebek & Çocuk Butik başvurusu alındı', $mail->envelope()->subject);
        $this->assertStringContainsString('Merhaba Buse Karaca, referansınız '.$application->reference.'.', $html);
        $this->assertStringContainsString('siparis@lilabutik.test adresine yazacağız.', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_document_upload_is_refused_when_nothing_requested(): void
    {
        $application = $this->applicationInReview();

        $this->api()->post("/api/bagisto/tenant-applications/{$application->public_token}/documents", [
            'vergi_levhasi' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_open_account_creates_inactive_tenant_and_invitation(): void
    {
        $application = $this->openAccount($this->applicationInReview());
        $tenant = Tenant::where('code', 'BAYI-0001')->firstOrFail();

        $this->assertSame('approved', $application->status);
        $this->assertSame($tenant->id, $application->converted_tenant_id);
        $this->assertSame(64, strlen($application->invitation_token));
        $this->assertTrue($application->invitationIsOpen());
        $this->assertFalse((bool) $tenant->is_active);
        $this->assertSame('siparis@lilabutik.test', $tenant->primaryUser->email);
        $this->assertSame('Buse Karaca', $tenant->primaryUser->name);

        Queue::assertNotPushed(PushTenantToBagisto::class); // pasif tenant → Bagisto'da henüz hesap yok
        Mail::assertQueued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'account_opened' && $mail->hasTo('siparis@lilabutik.test'));
    }

    public function test_invitation_completion_keeps_a_separately_entered_shipping_address(): void
    {
        $invitation = $this->openAccount($this->applicationInReview())->invitation_token;
        $tenant = Tenant::where('code', 'BAYI-0001')->firstOrFail();

        // Hesap açılışında başvuru adresi üç türe de kopyalanır; personel sevk adresini ayrıca girer.
        $this->assertSame(3, $tenant->addresses()->count());
        app(TenantService::class)->syncAddresses($tenant, [
            'shipping' => ['address' => 'Depo Sok. No:3', 'district' => 'Tuzla', 'city' => 'İstanbul'],
        ]);

        $this->api()->postJson("/api/bagisto/tenant-applications/invitations/{$invitation}/complete", $this->completion([
            'address' => 'Yeni Fatura Cad. No:1',
        ]))->assertOk();

        $tenant = $tenant->fresh();
        $this->assertSame('Yeni Fatura Cad. No:1', $tenant->addressOf('billing')->address);
        $this->assertSame('Yeni Fatura Cad. No:1', $tenant->addressOf('return')->address); // hâlâ fatura kopyasıydı
        $this->assertSame('Depo Sok. No:3', $tenant->addressOf('shipping')->address);     // personelin girdiği korunur
    }

    public function test_open_account_is_refused_before_document_review(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();

        $this->flushHeaders() // api() Accept: application/json başlığını taşımasın; admin ekranı session hatası alır
            ->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/open-account", ['code' => 'BAYI-0001'])
            ->assertSessionHasErrors('application');

        $this->assertSame(0, Tenant::count());
    }

    public function test_invitation_completion_activates_tenant_and_returns_customer_payload(): void
    {
        $invitation = $this->openAccount($this->applicationInReview())->invitation_token;

        $this->api()->get("/api/bagisto/tenant-applications/invitations/{$invitation}")
            ->assertOk()
            ->assertJsonPath('data.email', 'siparis@lilabutik.test')
            ->assertJsonPath('data.contact_person', 'Buse Karaca');

        $this->api()->postJson("/api/bagisto/tenant-applications/invitations/{$invitation}/complete", $this->completion())
            ->assertOk()
            ->assertJsonPath('data.customer.email', 'siparis@lilabutik.test')
            ->assertJsonPath('data.customer.first_name', 'Buse')
            ->assertJsonPath('data.customer.last_name', 'Karaca Yılmaz')
            ->assertJsonPath('data.customer.shipping_agreement_type', 'platform')
            // Teyit edilen adres fatura/sevk/iade adreslerine yazılır (sevk/iade henüz fatura kopyası).
            ->assertJsonCount(3, 'data.customer.addresses')
            ->assertJsonPath('data.customer.addresses.0.type', 'billing')
            ->assertJsonPath('data.customer.addresses.0.postcode', '34728')
            ->assertJsonPath('data.customer.addresses.1.type', 'shipping')
            ->assertJsonPath('data.customer.addresses.1.postcode', '34728');

        $tenant = Tenant::where('code', 'BAYI-0001')->first();
        $application = TenantApplication::first();

        $this->assertTrue((bool) $tenant->is_active);
        $this->assertNotNull($tenant->shipping_terms_accepted_at);
        $this->assertNotNull($application->completed_at);
        $this->assertNull($application->invitation_token);
        Queue::assertPushed(PushTenantToBagisto::class);
        Notification::assertSentTo($this->staff, TenantApplicationActivityNotification::class, 3); // ön başvuru, belgeler, tamamlandı

        $this->api()->get("/api/bagisto/tenant-applications/invitations/{$invitation}")->assertStatus(410);
    }

    public function test_own_shipping_agreement_requires_carrier(): void
    {
        $invitation = $this->openAccount($this->applicationInReview())->invitation_token;

        $this->api()->postJson(
            "/api/bagisto/tenant-applications/invitations/{$invitation}/complete",
            $this->completion(['shipping_agreement_type' => 'own', 'shipping_terms_accepted' => null])
        )->assertStatus(422)->assertJsonValidationErrors(['carrier_id']);
    }

    public function test_expired_invitation_is_gone_and_can_be_reissued(): void
    {
        $application = $this->openAccount($this->applicationInReview());
        $old = $application->invitation_token;

        $application->update(['invitation_expires_at' => now()->subMinute()]);

        $this->api()->get("/api/bagisto/tenant-applications/invitations/{$old}")->assertStatus(410);

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/resend-invitation")->assertRedirect();

        $fresh = $application->fresh();
        $this->assertNotSame($old, $fresh->invitation_token);
        $this->api()->get("/api/bagisto/tenant-applications/invitations/{$fresh->invitation_token}")->assertOk();
    }

    public function test_invitation_mail_uses_stitch_template_with_fresh_link(): void
    {
        config(['bagisto.sync.base_url' => 'https://store.test']);

        $application = $this->openAccount($this->applicationInReview());

        $mail = new DealerApplicationMail($application, 'account_opened');
        $html = $mail->render();

        $this->assertStringContainsString('Hesap Açıldı • #'.$application->reference, $html);
        $this->assertStringContainsString('Sayın Buse Karaca,', $html);
        $this->assertStringContainsString('B2B Dropshipping', $html);
        $this->assertStringContainsString('https://store.test/bayilik-basvurusu/uyelik/'.$application->invitation_token, $html);
        $this->assertStringContainsString('Üyeliğimi Tamamla', $html);
        $this->assertSame("Bayi hesabınız açıldı — üyeliğinizi tamamlayın (#{$application->reference})", $mail->envelope()->subject);

        // Yeniden gönderimde yeni bağlantılı ikinci bir davet kuyruğa girer.
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/resend-invitation");
        $this->assertCount(2, Mail::queued(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->stage === 'account_opened'));
    }

    public function test_document_preview_is_served_inline_for_reviewers_only(): void
    {
        $application = $this->applicationInReview();
        $document = $application->documents->firstWhere('type', 'vergi_levhasi');

        $response = $this->actingAs($this->staff)->get("/tenants/applications/{$application->id}/documents/{$document->id}/preview")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        // Önizlenemeyen tür (ör. uzantısı değiştirilmiş dosya) inline sunulmaz.
        $other = $application->documents()->create(['type' => 'diger', 'path' => "tenant-applications/{$application->id}/x.html", 'original_filename' => 'x.html', 'mime_type' => 'text/html', 'size' => 4]);
        Storage::put($other->path, '<b>x</b>');
        $this->actingAs($this->staff)->get("/tenants/applications/{$application->id}/documents/{$other->id}/preview")->assertStatus(415);

        // Başka başvurunun belgesi ve yetkisiz kullanıcı.
        $this->actingAs($this->staff)->get("/tenants/applications/".($application->id + 1)."/documents/{$document->id}/preview")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/tenants/applications/{$application->id}/documents/{$document->id}/preview")->assertForbidden();
    }

    public function test_application_file_prints_full_details_after_account_opened(): void
    {
        $application = $this->openAccount($this->applicationInReview());

        $this->actingAs($this->staff)->get("/tenants/applications/{$application->id}/print")
            ->assertOk()
            ->assertSee($application->reference)
            ->assertSee('Açılan iş ortağı hesabı')
            ->assertSee('BAYI-0001')
            ->assertSee('TR54 0006 2000 1122 3344 5566 77')
            ->assertSee('Çerçeve sözleşme');

        $this->actingAs(User::factory()->create())->get("/tenants/applications/{$application->id}/print")->assertForbidden();
    }

    public function test_open_account_applies_default_discount_and_drops_credit_while_cari_is_off(): void
    {
        $application = $this->openAccount($this->applicationInReview()); // credit_limit: 50000 gönderir

        $tenant = Tenant::findOrFail($application->converted_tenant_id);
        $this->assertEquals(Tenant::DEFAULT_DISCOUNT_RATE, (float) $tenant->discount_rate);
        $this->assertEquals(0, (float) $tenant->credit_limit);
    }

    public function test_open_account_keeps_credit_limit_and_given_discount_while_cari_is_on(): void
    {
        Setting::setGroup('bagisto_features', ['cari_account' => true]);
        $application = $this->applicationInReview();

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/open-account", ['code' => 'BAYI-0001', 'credit_limit' => 50000, 'discount_rate' => 30])
            ->assertSessionHasNoErrors();

        $tenant = Tenant::findOrFail($application->fresh()->converted_tenant_id);
        $this->assertEquals(50000, (float) $tenant->credit_limit);
        $this->assertEquals(30, (float) $tenant->discount_rate);
    }

    public function test_suggested_code_skips_codes_already_taken(): void
    {
        $application = $this->applicationInReview();
        $base = 'BAYI-'.str_pad((string) $application->id, 4, '0', STR_PAD_LEFT);
        $service = app(\Modules\Tenant\Services\TenantApplicationService::class);

        $this->assertSame($base, $service->suggestCode($application));

        Tenant::factory()->create(['code' => $base]);
        $this->assertSame("{$base}-2", $service->suggestCode($application));
    }

    public function test_unknown_token_is_404(): void
    {
        $this->api()->get('/api/bagisto/tenant-applications/'.str_repeat('a', 48))->assertStatus(404);
    }
}
