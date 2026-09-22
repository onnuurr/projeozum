<?php

namespace App\Contracts;

use App\DTO\SmsSendResult;

/**
 * SMS gönderim sağlayıcısı sözleşmesi. Tek somut sürücü iletiMerkezi
 * (App\Services\Sms\IletiMerkeziSmsProvider) — key/hash/sender yapılandırılmamışsa
 * App\Services\Sms\NullSmsProvider (hiçbir dış istek atmaz) kullanılır. Bkz.
 * AppServiceProvider::register() (aynı desen: Modules\Finance\Providers\
 * FinanceServiceProvider'ın EInvoiceProviderInterface binding'i).
 *
 * Yalnız İŞLEMSEL SMS için (OTP, fason/sipariş bildirimi, hesap işlemi).
 * Pazarlama SMS'i (İYS onaylı liste gerektiren `order.iys=1` akışı) bu
 * arayüzün kapsamında DEĞİL — bilinçli olarak desteklenmiyor; ihtiyaç
 * doğarsa ayrı bir metotla eklenmeli, sessizce genişletilmemeli.
 */
interface SmsProviderInterface
{
    public function send(string $phone, string $message): SmsSendResult;
}
