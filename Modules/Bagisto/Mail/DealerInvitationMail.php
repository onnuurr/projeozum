<?php

namespace Modules\Bagisto\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayi portalı başvurusunda resmi evrak onaylanıp hesap açılınca başvuru sahibine giden üyelik
 * tamamlama daveti (Stitch "Ozm Kids B2B Sipariş & Sevkiyat E-Postası (Saf E-Posta Şablonu)" tasarımı).
 *
 * Model kuyrukta id olarak saklanır ve gönderim anında taze okunur: davet bu arada yeniden
 * üretildiyse güncel bağlantı gider, gizli token kuyruğa yazılmaz.
 */
class DealerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TenantApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bayi hesabınız açıldı — üyeliğinizi tamamlayın (#{$this->application->reference})",
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('bagisto.sync.base_url'), '/');
        $details = $this->application->details ?? [];

        return new Content(
            view: 'bagisto::emails.dealer-invitation',
            with: [
                'application' => $this->application,
                'invitationUrl' => $base.'/bayilik-basvurusu/uyelik/'.$this->application->invitation_token,
                'portalUrl' => $base.'/customer/login',
                'dealerModel' => $details['dealer_model'] ?? null,
                'supportEmail' => config('bagisto.invitation_mail.support_email') ?: config('mail.from.address'),
                'advisor' => config('bagisto.invitation_mail.advisor'),
            ],
        );
    }
}
