<?php

namespace Modules\Bagisto\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Bagisto\Services\DealerMailContactService;
use Modules\Bagisto\Services\ReturnMailTemplateService;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;

/**
 * Bayiye iade kararı / fatura reddi / para iadesi e-postası. Tasarım başvuru e-postalarıyla ortak
 * (bagisto::emails.return-status → layouts.dealer); konu, giriş ve alt paragraf
 * ReturnMailTemplateService'ten gelir. $stage bir şablon adımıdır (bkz. ReturnMailTemplateService::stage()).
 *
 * Kuyruklanmaz: zaten NotifyTenantOfReturn job'ının içinden gönderilir. $template yalnızca
 * önizleme / test gönderiminde (kaydedilmemiş taslak metin) verilir.
 */
class ReturnStatusMail extends Mailable
{
    /**
     * @param  array{subject: string, intro: string, body?: string|null}|null  $template
     */
    public function __construct(
        public OrderReturn $orderReturn,
        public string $stage,
        public ?Tenant $tenant = null,
        public ?array $template = null,
    ) {
        $this->tenant ??= $orderReturn->tenant_id ? Tenant::find($orderReturn->tenant_id) : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        $base = rtrim((string) config('bagisto.sync.base_url'), '/');
        $return = $this->orderReturn;

        return new Content(
            view: 'bagisto::emails.return-status',
            with: [
                ...$this->texts(),
                'stage' => $this->stage,
                'return' => $return,
                'name' => $this->tenant?->contact_person ?: $this->tenant?->name ?: 'Değerli iş ortağımız',
                'companyName' => $this->tenant?->name,
                'orderRef' => $return->order?->order_no ?? $return->bagisto_order_id,
                'amount' => ReturnMailTemplateService::money($return->approved_amount),
                'refundMethodLabel' => OrderReturn::refundMethods()[$return->refund_method] ?? null,
                'note' => match ($this->stage) {
                    ReturnMailTemplateService::REJECTED => $return->decision_note,
                    ReturnMailTemplateService::INVOICE_REJECTED => $return->invoice_review_note,
                    default => null,
                },
                'returnUrl' => $base.'/customer/account/rma/view/'.$return->bagisto_rma_id,
                'portalUrl' => $base.'/customer/login',
                'contact' => app(DealerMailContactService::class)->contact(),
            ],
        );
    }

    /** @return array{subject: string, intro: string, body: string} Etiketleri doldurulmuş metinler. */
    private function texts(): array
    {
        $templates = app(ReturnMailTemplateService::class);

        return $templates->texts($this->stage, $templates->variables($this->orderReturn, $this->tenant, $this->stage), $this->template);
    }
}
