<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Bagisto\Jobs\NotifyTenantOfReturn;
use Modules\Bagisto\Mail\DealerApplicationMail;
use Modules\Bagisto\Mail\ReturnStatusMail;
use Modules\Bagisto\Services\DealerMailContactService;
use Modules\Bagisto\Services\DealerMailTemplates;
use Modules\Bagisto\Services\DealerMailTemplateService;
use Modules\Bagisto\Services\ReturnMailTemplateService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bayi E-postaları: başvuru ve iade e-postası metinlerinin yönetimi (grup bazlı yetki).
 */
class DealerMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();

        Permission::firstOrCreate(['name' => 'tenant-application-mail.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'order-return-mail.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@ozum.test']);
        $this->admin->givePermissionTo(['tenant-application-mail.manage', 'order-return-mail.manage']);
    }

    /** @return array<string, array<string, mixed>> */
    private function templates(array $overrides = [], string $service = DealerMailTemplateService::class): array
    {
        /** @var DealerMailTemplates $group */
        $group = app($service);

        $templates = collect($group->catalog())
            ->map(fn (array $stage) => ['enabled' => true, ...$stage['defaults']])
            ->all();

        return [$group->key() => array_replace_recursive($templates, $overrides)];
    }

    public function test_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/dealer-mail-templates')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/dealer-mail-templates', [])->assertForbidden();
    }

    public function test_index_lists_all_stages_with_defaults(): void
    {
        $this->actingAs($this->admin)->get('/dealer-mail-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Bagisto::DealerMailTemplates', false) // modül sayfası: Inertia test bulucusu Modül::Sayfa çözmüyor
                ->has('groups', 2)
                ->where('groups.0.key', 'applications')
                ->has('groups.0.stages', 6)
                ->where('groups.0.stages.0.key', 'received')
                ->where('groups.0.stages.0.enabled', true)
                ->where('groups.0.stages.5.key', 'account_opened')
                ->where('groups.0.stages.5.tags.4.tag', 'invitation.expires_at')
                ->where('groups.1.key', 'returns')
                ->has('groups.1.stages', 5)
                ->where('groups.1.stages.4.tags.5.tag', 'refund.method'));
    }

    public function test_update_saves_texts_and_contact(): void
    {
        $this->actingAs($this->admin)->put('/dealer-mail-templates', [
            'templates' => $this->templates([
                'rejected' => ['enabled' => false, 'subject' => 'Başvurunuz hk. {{application.reference}}'],
            ]),
            'contact' => ['advisor_name' => 'Deniz Kaya', 'advisor_whatsapp' => '905321234567'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $service = app(DealerMailTemplateService::class);

        $this->assertFalse($service->template('rejected')['enabled']);
        $this->assertSame('Başvurunuz hk. {{application.reference}}', $service->template('rejected')['subject']);
        $this->assertTrue($service->template('received')['enabled']);
        $this->assertSame('Deniz Kaya', app(DealerMailContactService::class)->contact()['advisor_name']);
    }

    public function test_update_rejects_unknown_tags(): void
    {
        $this->actingAs($this->admin)->put('/dealer-mail-templates', [
            'templates' => $this->templates([
                'received' => ['intro' => 'Merhaba {{user.surname}}'],
                // Davet tarihi yalnızca hesap açıldı e-postasında var.
                'rejected' => ['body' => 'Son tarih {{invitation.expires_at}}'],
            ]),
        ])->assertSessionHasErrors(['templates.applications.received.intro', 'templates.applications.rejected.body']);
    }

    public function test_preview_renders_draft_with_sample_application(): void
    {
        $this->actingAs($this->admin)->postJson('/dealer-mail-templates/preview', [
            'group' => 'applications',
            'stage' => 'account_opened',
            'template' => ['subject' => 'Hoş geldiniz {{user.name}}', 'intro' => '{{company.name}} hesabı açıldı.', 'body' => 'Son gün: {{invitation.expires_at}}'],
        ])
            ->assertOk()
            ->assertJsonPath('subject', 'Hoş geldiniz Ayşe Yılmaz')
            ->assertJson(fn ($json) => $json
                ->where('html', fn ($html) => str_contains($html, 'Lila Bebek &amp; Çocuk Butik hesabı açıldı.')
                    && str_contains($html, 'Üyeliğimi Tamamla')
                    && ! str_contains($html, '{{'))
                ->etc());
    }

    public function test_preview_validates_tags(): void
    {
        $this->actingAs($this->admin)->postJson('/dealer-mail-templates/preview', [
            'group' => 'applications',
            'stage' => 'received',
            'template' => ['subject' => '{{foo}}', 'intro' => 'x'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['template.subject']);
    }

    public function test_test_mail_is_sent_to_current_user(): void
    {
        $this->actingAs($this->admin)->postJson('/dealer-mail-templates/test', [
            'group' => 'applications',
            'stage' => 'missing_documents',
            'template' => ['subject' => 'Test', 'intro' => 'Giriş', 'body' => null],
        ])->assertOk();

        Mail::assertSent(DealerApplicationMail::class, fn (DealerApplicationMail $mail) => $mail->hasTo('admin@ozum.test') && $mail->stage === 'missing_documents');
    }

    public function test_groups_are_limited_to_users_permissions(): void
    {
        $returnsOnly = User::factory()->create();
        $returnsOnly->givePermissionTo('order-return-mail.manage');

        $this->actingAs($returnsOnly)->get('/dealer-mail-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Bagisto::DealerMailTemplates', false)
                ->has('groups', 1)
                ->where('groups.0.key', 'returns'));

        // Yetkisi olmayan grubun metinleri yok sayılır.
        $this->actingAs($returnsOnly)->put('/dealer-mail-templates', [
            'templates' => [
                ...$this->templates(['rejected' => ['enabled' => false]]),
                ...$this->templates(['rejected' => ['enabled' => false]], ReturnMailTemplateService::class),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(app(DealerMailTemplateService::class)->template('rejected')['enabled']);
        $this->assertFalse(app(ReturnMailTemplateService::class)->template('rejected')['enabled']);

        $this->actingAs($returnsOnly)->postJson('/dealer-mail-templates/preview', [
            'group' => 'applications',
            'stage' => 'received',
            'template' => ['subject' => 'x', 'intro' => 'y'],
        ])->assertForbidden();
    }

    public function test_return_texts_are_saved_and_validated(): void
    {
        $this->actingAs($this->admin)->put('/dealer-mail-templates', [
            'templates' => $this->templates([
                'approved' => ['subject' => 'Onay {{order.number}} / {{return.amount}}'],
                // İade yöntemi yalnızca ödeme e-postasında var.
                'rejected' => ['body' => '{{refund.method}}'],
            ], ReturnMailTemplateService::class),
        ])->assertSessionHasErrors(['templates.returns.rejected.body']);

        $this->actingAs($this->admin)->put('/dealer-mail-templates', [
            'templates' => $this->templates([
                'approved' => ['subject' => 'Onay {{order.number}} / {{return.amount}}'],
            ], ReturnMailTemplateService::class),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Onay {{order.number}} / {{return.amount}}', app(ReturnMailTemplateService::class)->template('approved')['subject']);
    }

    public function test_return_preview_renders_each_stage_with_sample(): void
    {
        foreach (array_keys(app(ReturnMailTemplateService::class)->catalog()) as $stage) {
            $defaults = app(ReturnMailTemplateService::class)->catalog()[$stage]['defaults'];

            $this->actingAs($this->admin)->postJson('/dealer-mail-templates/preview', [
                'group' => 'returns',
                'stage' => $stage,
                'template' => $defaults,
            ])
                ->assertOk()
                ->assertJson(fn ($json) => $json
                    ->where('subject', fn ($subject) => str_contains($subject, 'OZM-2026-05310'))
                    ->where('html', fn ($html) => str_contains($html, 'Sayın Ayşe Yılmaz')
                        && str_contains($html, 'B2B Bayi İadeleri')
                        && ! str_contains($html, '{{'))
                    ->etc());
        }

        $this->actingAs($this->admin)->postJson('/dealer-mail-templates/preview', [
            'group' => 'returns',
            'stage' => 'refunded',
            'template' => ['subject' => 'x', 'intro' => '{{return.amount}} · {{refund.method}}'],
        ])->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, '1.280,00 ₺ · Havale / EFT') && str_contains($html, 'EFT-2026-004812'));
    }

    public function test_return_test_mail_is_sent_to_current_user(): void
    {
        $this->actingAs($this->admin)->postJson('/dealer-mail-templates/test', [
            'group' => 'returns',
            'stage' => 'invoice_rejected',
            'template' => ['subject' => 'Test', 'intro' => 'Giriş'],
        ])->assertOk();

        Mail::assertSent(ReturnStatusMail::class, fn (ReturnStatusMail $mail) => $mail->hasTo('admin@ozum.test') && $mail->stage === 'invoice_rejected');
    }

    public function test_approval_uses_invoice_template_when_invoice_required(): void
    {
        $service = app(ReturnMailTemplateService::class);
        [$return] = $service->sample('approved', 'x@y.z');

        $this->assertSame('approved', $service->stage($return, NotifyTenantOfReturn::EVENT_APPROVED));

        $return->invoice_required = true;
        $this->assertSame('approved_invoice', $service->stage($return, NotifyTenantOfReturn::EVENT_APPROVED));
    }
}
