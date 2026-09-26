<?php

namespace Modules\Bagisto\Jobs;

use App\Contracts\SmsProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Bagisto\Mail\ReturnStatusMail;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;
use Throwable;

/**
 * Bayiye iade kararı / fatura reddi / para iadesi bildirimi: e-posta (bayi portalı giriş
 * e-postası) + SMS (yetkili telefonu). Biri başarısız olursa diğeri yine gider; SMS hatası
 * job'ı tekrar denetmez (e-posta iki kez gitmesin).
 */
class NotifyTenantOfReturn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const EVENT_APPROVED = 'approved';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_INVOICE_REJECTED = 'invoice_rejected';
    public const EVENT_REFUNDED = 'refunded';

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(
        protected int $orderReturnId,
        protected string $event,
    ) {}

    public function handle(SmsProviderInterface $sms): void
    {
        $return = OrderReturn::with('order')->find($this->orderReturnId);
        $tenant = $return?->tenant_id ? Tenant::find($return->tenant_id) : null;

        if (! $tenant) {
            return;
        }

        $email = $tenant->users()->value('email') ?: $tenant->email;
        if ($email) {
            Mail::to($email)->send(new ReturnStatusMail($return, $this->event, $tenant->contact_person ?: $tenant->name));
        }

        $phone = $tenant->contact_phone ?: $tenant->phone;
        if (! $phone) {
            return;
        }

        try {
            $result = $sms->send($phone, $this->smsText($return));
            if (! $result->success) {
                Log::warning('İade bildirimi: SMS gönderilemedi.', ['order_return_id' => $return->id, 'error' => $result->errorMessage]);
            }
        } catch (Throwable $e) {
            Log::warning('İade bildirimi: SMS gönderilemedi.', ['order_return_id' => $return->id, 'error' => $e->getMessage()]);
        }
    }

    private function smsText(OrderReturn $return): string
    {
        $ref = 'Siparis '.($return->order?->order_no ?? $return->bagisto_order_id);
        $amount = number_format((float) $return->approved_amount, 2, ',', '.').' TL';

        return match ($this->event) {
            self::EVENT_APPROVED => $return->invoice_required
                ? "Ozm Kids: {$ref} iade talebiniz {$amount} olarak onaylandi. Odemenin yapilmasi icin bayi portalindan iade faturanizi yukleyiniz."
                : "Ozm Kids: {$ref} iade talebiniz {$amount} olarak onaylandi. Odemeniz en kisa surede yapilacaktir.",
            self::EVENT_REJECTED => "Ozm Kids: {$ref} iade talebiniz reddedildi. Detaylar bayi portalinda.",
            self::EVENT_INVOICE_REJECTED => "Ozm Kids: {$ref} iade faturaniz kabul edilmedi. Aciklamayi bayi portalinda gorup yeni fatura yukleyiniz.",
            self::EVENT_REFUNDED => "Ozm Kids: {$ref} iadeniz {$amount} olarak odendi (".(OrderReturn::refundMethods()[$return->refund_method] ?? '').').',
            default => "Ozm Kids: {$ref} iade talebinizde guncelleme var. Detaylar bayi portalinda.",
        };
    }
}
