<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Bagisto\Jobs\PushTenantApplicationStageToBagisto;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Bagisto\Mail\DealerInvitationMail;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Notifications\TenantApplicationActivityNotification;
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
    }

    public function test_pre_application_requires_declaration(): void
    {
        $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication([
            'details' => ['consents' => ['declaration' => '0']],
        ]))->assertStatus(422)->assertJsonValidationErrors(['details.consents.declaration']);
    }

    public function test_official_step_is_closed_until_staff_opens_it(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');

        $this->api()->post("/api/bagisto/tenant-applications/{$token}/official", $this->officialStep())->assertStatus(409);
    }

    public function test_opening_document_stage_notifies_applicant_via_store(): void
    {
        $token = $this->api()->post('/api/bagisto/tenant-applications', $this->preApplication())->json('data.token');
        $application = TenantApplication::where('public_token', $token)->first();

        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/open-documents")->assertRedirect();

        $this->assertSame('documents_requested', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->documents_requested_at);
        Queue::assertPushed(PushTenantApplicationStageToBagisto::class);
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

        Queue::assertPushed(PushTenantApplicationStageToBagisto::class, 1); // yalnız belge aşaması; davet mağazaya gitmez
        Queue::assertNotPushed(PushTenantToBagisto::class); // pasif tenant → Bagisto'da henüz hesap yok
        Mail::assertQueued(DealerInvitationMail::class, fn (DealerInvitationMail $mail) => $mail->hasTo('siparis@lilabutik.test'));
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
            ->assertJsonPath('data.customer.address.postcode', '34728');

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

        $mail = new DealerInvitationMail($application);
        $html = $mail->render();

        $this->assertStringContainsString('Hesap Açıldı • #'.$application->reference, $html);
        $this->assertStringContainsString('Sayın Buse Karaca,', $html);
        $this->assertStringContainsString('B2B Dropshipping', $html);
        $this->assertStringContainsString('https://store.test/bayilik-basvurusu/uyelik/'.$application->invitation_token, $html);
        $this->assertStringContainsString('Üyeliğimi Tamamla', $html);
        $this->assertSame("Bayi hesabınız açıldı — üyeliğinizi tamamlayın (#{$application->reference})", $mail->envelope()->subject);

        // Yeniden gönderimde yeni bağlantılı ikinci bir davet kuyruğa girer.
        $this->actingAs($this->staff)->post("/tenants/applications/{$application->id}/resend-invitation");
        Mail::assertQueued(DealerInvitationMail::class, 2);
    }

    public function test_other_stages_are_still_pushed_to_store(): void
    {
        config(['bagisto.sync.base_url' => 'https://store.test', 'bagisto.sync.webhook_secret' => 'secret']);
        Http::fake(['store.test/*' => Http::response(['message' => 'ok'])]);

        $application = $this->applicationInReview();

        (new PushTenantApplicationStageToBagisto($application->id, 'documents_requested'))->handle(app(BagistoSyncClient::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://store.test/api/saas-sync/dealer-applications'
            && $request['event'] === 'documents_requested'
            && $request['status_token'] === $application->public_token
            && $request['dealer_model'] === 'dropshipping'
            && $request->hasHeader('X-Saas-Signature'));
    }

    public function test_unknown_token_is_404(): void
    {
        $this->api()->get('/api/bagisto/tenant-applications/'.str_repeat('a', 48))->assertStatus(404);
    }
}
