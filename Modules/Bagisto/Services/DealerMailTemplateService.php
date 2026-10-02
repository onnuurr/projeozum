<?php

namespace Modules\Bagisto\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Modules\Bagisto\Mail\DealerApplicationMail;
use Modules\Tenant\Events\TenantApplicationStageChanged as Stage;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;

/**
 * Bayi E-postaları › Başvuru: bayi portalı başvurusunun adım e-postaları (tasarım
 * bagisto::emails.dealer-application). Metinler platform ayarlarının 'dealer_mail' grubunda
 * saklanır; ortak davranış için bkz. DealerMailTemplates.
 */
class DealerMailTemplateService extends DealerMailTemplates
{
    public function key(): string
    {
        return 'applications';
    }

    public function label(): string
    {
        return 'Başvuru';
    }

    public function permission(): string
    {
        return 'tenant-application-mail.manage';
    }

    protected function settingsGroup(): string
    {
        return 'dealer_mail';
    }

    public function catalog(): array
    {
        return [
            Stage::RECEIVED => [
                'label' => 'Ön başvuru alındı',
                'description' => 'Başvuru sahibi bayi portalındaki formu gönderdiğinde.',
                'defaults' => [
                    'subject' => 'Bayilik başvurunuz alındı (#{{application.reference}})',
                    'intro' => '{{company.name}} adına ilettiğiniz bayilik ön başvurusu bize ulaştı.',
                    'body' => 'B2B ekibimiz başvurunuzu inceleyip görüşme için kısa süre içinde sizinle iletişime geçecek. Başvurunuzun durumunu aşağıdaki bağlantıdan dilediğiniz zaman takip edebilirsiniz.',
                ],
            ],
            Stage::DOCUMENTS_REQUESTED => [
                'label' => 'Resmi belge adımı açıldı',
                'description' => 'Görüşme sonrası "Resmi belge aşamasını aç" dendiğinde.',
                'defaults' => [
                    'subject' => 'Bayilik başvurunuz: resmi belge adımı açıldı (#{{application.reference}})',
                    'intro' => '{{company.name}} için ön değerlendirmemiz ve görüşmemiz tamamlandı.',
                    'body' => 'Başvurunuzun resmi evrak incelemesine geçebilmesi için başvuru sayfanızdan vergi levhası, imza sirküleri, banka bilgilerinizi ve sözleşme onayınızı iletin.',
                ],
            ],
            Stage::DOCUMENTS_REMINDER => [
                'label' => 'Resmi belge hatırlatması',
                'description' => 'Resmi belge adımı açık ama bayi henüz göndermediğinde, başvuru ayrıntısındaki "Hatırlatma gönder" ile.',
                'defaults' => [
                    'subject' => 'Hatırlatma: bayilik başvurunuz resmi belgelerinizi bekliyor (#{{application.reference}})',
                    'intro' => '{{company.name}} için açtığımız resmi belge adımı henüz tamamlanmadı.',
                    'body' => 'Başvurunuzun resmi evrak incelemesine geçebilmesi için başvuru sayfanızdan vergi levhası, imza sirküleri, banka bilgilerinizi ve sözleşme onayınızı iletin. Herhangi bir sorunla karşılaşırsanız bizimle iletişime geçebilirsiniz.',
                ],
            ],
            Stage::MISSING_DOCUMENTS => [
                'label' => 'Ek belge talebi',
                'description' => 'Evrak incelemesinde eksik belge istendiğinde. İstenen belgeler ve not e-postada ayrıca listelenir.',
                'defaults' => [
                    'subject' => 'Bayilik başvurunuz: ek belge talebi (#{{application.reference}})',
                    'intro' => '{{company.name}} başvurusunun evrak incelemesinde aşağıdaki belgelerin güncellenmesi gerekiyor:',
                    'body' => 'Belgeleri başvuru sayfanızdan yükleyebilirsiniz; tamamı yüklendiğinde başvurunuz otomatik olarak yeniden incelemeye alınır.',
                ],
            ],
            Stage::REJECTED => [
                'label' => 'Başvuru reddedildi',
                'description' => 'Başvuru reddedildiğinde. Girilen ret gerekçesi e-postada ayrıca gösterilir.',
                'defaults' => [
                    'subject' => 'Bayilik başvurunuz hakkında (#{{application.reference}})',
                    'intro' => '{{company.name}} adına ilettiğiniz bayilik başvurusu değerlendirme sonucunda onaylanmadı.',
                    'body' => 'Değerlendirme hakkında bilgi almak için destek hattımıza başvuru referans kodunuzla ulaşabilirsiniz.',
                ],
            ],
            Stage::ACCOUNT_OPENED => [
                'label' => 'Hesap açıldı (üyelik daveti)',
                'description' => 'Resmi evrak onaylanıp hesap açıldığında ve davet yeniden gönderildiğinde. Üyelik tamamlama bağlantısını içerir.',
                'defaults' => [
                    'subject' => 'Bayi hesabınız açıldı — üyeliğinizi tamamlayın (#{{application.reference}})',
                    'intro' => '{{company.name}} adına ilettiğiniz bayilik başvurusunun resmi evrak incelemesi tamamlandı ve bayi hesabınız açıldı. Aşağıdaki bağlantıyla parolanızı oluşturup birkaç bilgiyi teyit ettiğinizde bayi portalına giriş yaparak siparişe başlayabilirsiniz.',
                    'body' => 'Üyelik bağlantınız {{invitation.expires_at}} tarihine kadar geçerlidir.',
                ],
            ],
        ];
    }

    public function tags(string $stage): array
    {
        $tags = [
            'user.name' => 'Yetkili adı soyadı',
            'user.email' => 'Yetkili e-posta adresi',
            'company.name' => 'Firma adı',
            'application.reference' => 'Başvuru referans kodu',
        ];

        if ($stage === Stage::ACCOUNT_OPENED) {
            $tags['invitation.expires_at'] = 'Üyelik bağlantısının son geçerlilik tarihi';
        }

        return $tags;
    }

    /** @return array<string, string> */
    public function variables(TenantApplication $application, string $stage): array
    {
        $vars = [
            'user.name' => (string) ($application->contact_person ?: $application->name),
            'user.email' => (string) $application->contact_email,
            'company.name' => (string) $application->name,
            'application.reference' => $application->reference,
        ];

        if ($stage === Stage::ACCOUNT_OPENED) {
            $vars['invitation.expires_at'] = (string) $application->invitation_expires_at
                ?->timezone(config('app.timezone'))->locale('tr')->translatedFormat('j F Y');
        }

        return $vars;
    }

    /** Adım açıksa başvuru sahibine e-postayı kuyruğa alır. */
    public function send(TenantApplication $application, string $stage): bool
    {
        if (! array_key_exists($stage, $this->catalog()) || ! $this->template($stage)['enabled']) {
            return false;
        }

        if ($stage === Stage::ACCOUNT_OPENED && ! $application->invitationIsOpen()) {
            return false;
        }

        Mail::to($application->contact_email, $application->contact_person ?: $application->name)
            ->queue(new DealerApplicationMail($application, $stage));

        return true;
    }

    public function previewMail(string $stage, array $template, string $email): Mailable
    {
        return new DealerApplicationMail($this->sample($email), $stage, $template);
    }

    /** Önizleme ve test gönderimi için kaydedilmeyen örnek başvuru. */
    public function sample(string $email): TenantApplication
    {
        $application = new TenantApplication([
            'name' => 'Lila Bebek & Çocuk Butik',
            'contact_person' => 'Ayşe Yılmaz',
            'contact_email' => $email,
            'source' => TenantApplication::SOURCE_BAGISTO,
            'status' => TenantApplication::STATUS_APPROVED,
            'details' => ['dealer_model' => 'hibrit'],
            'missing_documents' => [TenantApplicationDocument::TYPE_TAX_CERTIFICATE, TenantApplicationDocument::TYPE_SIGNATURE_CIRCULAR],
            'missing_note' => 'Vergi levhanızın son yıl onaylı sürümünü yükleyin.',
            'rejection_reason' => 'Bölgenizde şu an yeni bayilik vermiyoruz.',
            'public_token' => str_repeat('0', 48),
            'invitation_token' => str_repeat('0', 64),
            'invitation_expires_at' => now()->addDays(TenantApplication::INVITATION_TTL_DAYS),
        ]);

        $application->id = 1234;
        $application->created_at = now();

        return $application;
    }
}
