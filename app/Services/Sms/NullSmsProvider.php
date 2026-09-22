<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use App\DTO\SmsSendResult;
use Illuminate\Support\Facades\Log;

/**
 * SMS sağlayıcısı yapılandırılmadığında (key/hash/sender eksik) tek sürücü.
 * Hiçbir dış istek atmaz — Modules\Finance\Services\EInvoice\NullEInvoiceProvider
 * ile aynı disiplin.
 */
class NullSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): SmsSendResult
    {
        Log::info('SMS sağlayıcısı henüz yapılandırılmadı; gönderim atlandı.', [
            'phone' => $phone,
        ]);

        return new SmsSendResult(success: false, errorMessage: 'SMS sağlayıcısı yapılandırılmadı.');
    }
}
