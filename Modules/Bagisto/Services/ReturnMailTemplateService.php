<?php

namespace Modules\Bagisto\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Modules\Bagisto\Jobs\NotifyTenantOfReturn;
use Modules\Bagisto\Mail\ReturnStatusMail;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;

/**
 * Bayi E-postaları › İade: bayiye iade kararı / fatura reddi / para iadesi e-postaları (tasarım
 * bagisto::emails.return-status). Metinler platform ayarlarının 'return_mail' grubunda saklanır;
 * ortak davranış için bkz. DealerMailTemplates.
 *
 * Onay e-postasının iki şablonu vardır: iade faturası gerekiyorsa 'approved_invoice', gerekmiyorsa
 * 'approved' (bkz. stage()). SMS metinleri bu sayfadan yönetilmez (NotifyTenantOfReturn).
 */
class ReturnMailTemplateService extends DealerMailTemplates
{
    public const APPROVED = 'approved';

    public const APPROVED_INVOICE = 'approved_invoice';

    public const REJECTED = 'rejected';

    public const INVOICE_REJECTED = 'invoice_rejected';

    public const REFUNDED = 'refunded';

    public function key(): string
    {
        return 'returns';
    }

    public function label(): string
    {
        return 'İade';
    }

    public function permission(): string
    {
        return 'order-return-mail.manage';
    }

    protected function settingsGroup(): string
    {
        return 'return_mail';
    }

    public function catalog(): array
    {
        return [
            self::APPROVED => [
                'label' => 'İade onaylandı',
                'description' => 'İade talebi onaylandığında ve iade faturası gerekmediğinde (ödeme doğrudan yapılır).',
                'defaults' => [
                    'subject' => 'İade talebiniz onaylandı — {{order.number}}',
                    'intro' => '{{order.number}} numaralı siparişinize ait iade talebiniz {{return.amount}} olarak onaylandı.',
                    'body' => 'Ödemeniz, siparişin ödeme yöntemine en kısa sürede yapılacaktır.',
                ],
            ],
            self::APPROVED_INVOICE => [
                'label' => 'İade onaylandı (fatura gerekli)',
                'description' => 'İade talebi onaylandığında ve sipariş faturalandırıldığı için bayiden iade faturası beklendiğinde. E-postada fatura yükleme uyarısı ayrıca gösterilir.',
                'defaults' => [
                    'subject' => 'İade talebiniz onaylandı — {{order.number}}',
                    'intro' => '{{order.number}} numaralı siparişinize ait iade talebiniz {{return.amount}} olarak onaylandı.',
                    'body' => 'Sipariş tarafımızca faturalandırıldığı için ödemenin yapılabilmesi adına iade faturanızı bayi portalındaki iade sayfasından yüklemeniz gerekiyor.',
                ],
            ],
            self::REJECTED => [
                'label' => 'İade reddedildi',
                'description' => 'İade talebi reddedildiğinde. Girilen karar notu e-postada ayrıca gösterilir.',
                'defaults' => [
                    'subject' => 'İade talebiniz reddedildi — {{order.number}}',
                    'intro' => '{{order.number}} numaralı siparişinize ait iade talebiniz incelendi ve kabul edilmedi. Gerekçeyi aşağıda bulabilirsiniz.',
                    'body' => 'Sorularınız için destek ekibimize iade talep numaranızla (#{{return.number}}) ulaşabilirsiniz.',
                ],
            ],
            self::INVOICE_REJECTED => [
                'label' => 'İade faturası kabul edilmedi',
                'description' => 'Bayinin yüklediği iade faturası reddedildiğinde. Kontrol notu e-postada ayrıca gösterilir.',
                'defaults' => [
                    'subject' => 'İade faturanız kabul edilmedi — {{order.number}}',
                    'intro' => '{{order.number}} numaralı siparişinizin iadesi için yüklediğiniz fatura kontrol edildi ve kabul edilmedi.',
                    'body' => 'Açıklamayı inceleyip bayi portalından düzeltilmiş faturayı yeniden yükleyebilirsiniz.',
                ],
            ],
            self::REFUNDED => [
                'label' => 'İade ödemesi yapıldı',
                'description' => 'İade ödemesi gerçekleştirildiğinde (cari hesaba alacak, karta iade ya da havale/EFT).',
                'defaults' => [
                    'subject' => 'İade ödemeniz yapıldı — {{order.number}}',
                    'intro' => '{{order.number}} numaralı siparişinize ait {{return.amount}} tutarındaki iade ödemeniz yapıldı ({{refund.method}}).',
                    'body' => '',
                ],
            ],
        ];
    }

    public function tags(string $stage): array
    {
        $tags = [
            'user.name' => 'Bayi yetkilisinin adı',
            'company.name' => 'Bayi firma adı',
            'order.number' => 'Sipariş numarası',
            'return.number' => 'İade talebi numarası',
            'return.amount' => 'Onaylanan iade tutarı',
        ];

        if ($stage === self::REFUNDED) {
            $tags['refund.method'] = 'İade yöntemi (ör. Cari hesaba alacak)';
        }

        return $tags;
    }

    /** Bildirim olayının şablon adımı; şablonu olmayan olay için null. */
    public function stage(OrderReturn $return, string $event): ?string
    {
        return match ($event) {
            NotifyTenantOfReturn::EVENT_APPROVED => $return->invoice_required ? self::APPROVED_INVOICE : self::APPROVED,
            NotifyTenantOfReturn::EVENT_REJECTED => self::REJECTED,
            NotifyTenantOfReturn::EVENT_INVOICE_REJECTED => self::INVOICE_REJECTED,
            NotifyTenantOfReturn::EVENT_REFUNDED => self::REFUNDED,
            default => null,
        };
    }

    /** @return array<string, string> */
    public function variables(OrderReturn $return, ?Tenant $tenant, string $stage): array
    {
        $vars = [
            'user.name' => (string) ($tenant?->contact_person ?: $tenant?->name ?: 'Değerli iş ortağımız'),
            'company.name' => (string) $tenant?->name,
            'order.number' => (string) ($return->order?->order_no ?? $return->bagisto_order_id),
            'return.number' => (string) $return->bagisto_rma_id,
            'return.amount' => self::money($return->approved_amount),
        ];

        if ($stage === self::REFUNDED) {
            $vars['refund.method'] = (string) (OrderReturn::refundMethods()[$return->refund_method] ?? '');
        }

        return $vars;
    }

    public static function money(mixed $amount): string
    {
        return $amount === null ? '' : number_format((float) $amount, 2, ',', '.').' ₺';
    }

    /**
     * Adım açıksa bayiye e-postayı hemen gönderir (NotifyTenantOfReturn zaten kuyrukta çalışır).
     */
    public function send(OrderReturn $return, Tenant $tenant, string $event, string $email): bool
    {
        $stage = $this->stage($return, $event);

        if ($stage === null || ! $this->template($stage)['enabled']) {
            return false;
        }

        Mail::to($email)->send(new ReturnStatusMail($return, $stage, $tenant));

        return true;
    }

    public function previewMail(string $stage, array $template, string $email): Mailable
    {
        [$return, $tenant] = $this->sample($stage, $email);

        return new ReturnStatusMail($return, $stage, $tenant, $template);
    }

    /**
     * Önizleme ve test gönderimi için kaydedilmeyen örnek iade ve bayi.
     *
     * @return array{0: OrderReturn, 1: Tenant}
     */
    public function sample(string $stage, string $email): array
    {
        $tenant = new Tenant(['name' => 'Lila Bebek & Çocuk Butik', 'contact_person' => 'Ayşe Yılmaz', 'email' => $email]);

        $return = new OrderReturn([
            'bagisto_rma_id' => 1042,
            'bagisto_order_id' => 5310,
            'status' => $stage === self::REJECTED ? OrderReturn::STATUS_REJECTED : OrderReturn::STATUS_APPROVED,
            'requested_amount' => 1450,
            'approved_amount' => $stage === self::REJECTED ? null : 1280,
            'decision_note' => $stage === self::REJECTED ? 'Ürünler kullanılmış ve etiketleri çıkarılmış olarak ulaştı.' : null,
            'invoice_required' => $stage === self::APPROVED_INVOICE || $stage === self::INVOICE_REJECTED,
            'invoice_review_note' => $stage === self::INVOICE_REJECTED ? 'Faturadaki alıcı unvanı hatalı; Ozm Kids unvanıyla yeniden düzenleyin.' : null,
            'refund_method' => $stage === self::REFUNDED ? OrderReturn::REFUND_BANK : null,
            'refund_reference' => $stage === self::REFUNDED ? 'EFT-2026-004812' : null,
        ]);

        $return->setRelation('order', (new Order)->forceFill(['order_no' => 'OZM-2026-05310']));

        return [$return, $tenant];
    }
}
