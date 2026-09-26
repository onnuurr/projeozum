<?php

namespace Modules\Bagisto\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Bagisto\Jobs\NotifyTenantOfReturn;
use Modules\Product\Models\OrderReturn;

/**
 * Bayiye iade kararı / fatura reddi / para iadesi e-postası. Kuyruklanmaz: zaten
 * NotifyTenantOfReturn job'ının içinden gönderilir.
 */
class ReturnStatusMail extends Mailable
{
    public function __construct(
        public OrderReturn $orderReturn,
        public string $event,
        public ?string $tenantName = null,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->orderReturn->order?->order_no ?? $this->orderReturn->bagisto_order_id;

        return new Envelope(subject: match ($this->event) {
            NotifyTenantOfReturn::EVENT_APPROVED => "İade talebiniz onaylandı — {$ref}",
            NotifyTenantOfReturn::EVENT_REJECTED => "İade talebiniz reddedildi — {$ref}",
            NotifyTenantOfReturn::EVENT_INVOICE_REJECTED => "İade faturanız kabul edilmedi — {$ref}",
            NotifyTenantOfReturn::EVENT_REFUNDED => "İade ödemeniz yapıldı — {$ref}",
            default => "İade talebinizde güncelleme — {$ref}",
        });
    }

    public function content(): Content
    {
        $base = rtrim((string) config('bagisto.sync.base_url'), '/');

        return new Content(
            view: 'bagisto::emails.return-status',
            with: [
                'return' => $this->orderReturn,
                'event' => $this->event,
                'tenantName' => $this->tenantName ?: 'Değerli iş ortağımız',
                'orderRef' => $this->orderReturn->order?->order_no ?? $this->orderReturn->bagisto_order_id,
                'portalUrl' => $base.'/customer/account/rma/view/'.$this->orderReturn->bagisto_rma_id,
                'refundMethodLabel' => OrderReturn::refundMethods()[$this->orderReturn->refund_method] ?? null,
            ],
        );
    }
}
