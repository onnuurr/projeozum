<?php

namespace Modules\Bagisto\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Bagisto\Services\DealerMailContactService;
use Modules\Bagisto\Services\DealerMailTemplateService;
use Modules\Tenant\Events\TenantApplicationStageChanged as Stage;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;

/**
 * Bayi portalı başvurusunun adım e-postası (ön başvuru alındı, resmi belge adımı, ek belge, red,
 * hesap açıldı / üyelik daveti). Tasarım tek ve sabit (bagisto::emails.dealer-application, Stitch
 * "Ozm Kids B2B Sipariş & Sevkiyat E-Postası" dili); konu, giriş ve alt paragraf
 * DealerMailTemplateService'ten gelir.
 *
 * Model kuyrukta id olarak saklanır ve gönderim anında taze okunur: davet bu arada yeniden
 * üretildiyse güncel bağlantı gider, gizli token kuyruğa yazılmaz. $template yalnızca önizleme /
 * test gönderiminde (kaydedilmemiş taslak metin) verilir.
 */
class DealerApplicationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject: string, intro: string, body?: string|null}|null  $template
     */
    public function __construct(
        public TenantApplication $application,
        public string $stage,
        public ?array $template = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        $base = rtrim((string) config('bagisto.sync.base_url'), '/');
        $application = $this->application;
        $details = $application->details ?? [];

        return new Content(
            view: 'bagisto::emails.dealer-application',
            with: [
                ...$this->texts(),
                'stage' => $this->stage,
                'name' => $application->contact_person ?: $application->name,
                'statusUrl' => $base.'/bayilik-basvurusu/durum/'.$application->public_token,
                'invitationUrl' => $base.'/bayilik-basvurusu/uyelik/'.$application->invitation_token,
                'portalUrl' => $base.'/customer/login',
                'dealerModel' => $details['dealer_model'] ?? null,
                'expires' => $application->invitation_expires_at?->timezone(config('app.timezone'))->locale('tr')->translatedFormat('j F Y'),
                'documents' => $this->stage === Stage::MISSING_DOCUMENTS
                    ? collect($application->missing_documents ?? [])->map(fn ($type) => TenantApplicationDocument::LABELS[$type] ?? $type)->values()->all()
                    : [],
                'note' => $this->stage === Stage::MISSING_DOCUMENTS ? $application->missing_note : null,
                'reason' => $this->stage === Stage::REJECTED ? $application->rejection_reason : null,
                'contact' => app(DealerMailContactService::class)->contact(),
            ],
        );
    }

    /** @return array{subject: string, intro: string, body: string} Etiketleri doldurulmuş metinler. */
    private function texts(): array
    {
        $templates = app(DealerMailTemplateService::class);

        return $templates->texts($this->stage, $templates->variables($this->application, $this->stage), $this->template);
    }
}
