<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use App\DTO\SmsSendResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iletiMerkezi SMS API sürücüsü (https://www.iletimerkezi.com/docs/api/send-sms).
 *
 * Kimlik doğrulama key+hash (kullanıcı adı/şifre DEĞİL) — panelden alınır.
 * `order.iys` her zaman "0" (işlemsel) gönderilir; pazarlama SMS'i desteklenmiyor
 * (bkz. SmsProviderInterface docblock'u).
 *
 * Bilinen hata kodları: 401 kimlik bilgisi geçersiz, 451 tekrarlanan
 * (sender+alıcı+metin) gönderim, 452 alıcı listesi eksik/hatalı konumda,
 * 466 telefon formatı geçersiz.
 */
class IletiMerkeziSmsProvider implements SmsProviderInterface
{
    private const SUCCESS_CODE = 200;

    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function send(string $phone, string $message): SmsSendResult
    {
        $number = $this->normalizePhone($phone);

        if ($number === null) {
            return new SmsSendResult(
                success: false,
                errorMessage: 'Geçersiz telefon numarası formatı: ' . $phone,
            );
        }

        $response = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->post('/send-sms/json', [
                'request' => [
                    'authentication' => [
                        'key'  => $this->config['key'],
                        'hash' => $this->config['hash'],
                    ],
                    'order' => [
                        'sender'  => $this->config['sender'],
                        // İşlemsel SMS — pazarlama listesi (iysList) gerektirmez.
                        'iys'     => '0',
                        'message' => [
                            'text'       => $message,
                            // API'nin kendi alan adı "receipents" (yazım hatası API'de de var,
                            // "receipents" != "recipients" — kasıtlı, dokümantasyona sadık kalındı).
                            'receipents' => [
                                'number' => [$number],
                            ],
                        ],
                    ],
                ],
            ]);

        $body   = $response->json();
        $status = $body['response']['status'] ?? [];
        $code   = (int) ($status['code'] ?? 0);

        if ($code !== self::SUCCESS_CODE) {
            Log::warning('iletiMerkezi SMS gönderimi başarısız.', [
                'phone'   => $number,
                'code'    => $code,
                'message' => $status['message'] ?? null,
                'http'    => $response->status(),
            ]);

            return new SmsSendResult(
                success: false,
                errorCode: $code !== 0 ? $code : null,
                errorMessage: $status['message'] ?? ('HTTP ' . $response->status()),
                rawResponse: is_array($body) ? $body : null,
            );
        }

        return new SmsSendResult(
            success: true,
            orderId: isset($body['response']['order']['id']) ? (string) $body['response']['order']['id'] : null,
            rawResponse: is_array($body) ? $body : null,
        );
    }

    /**
     * Kabul edilen girdi biçimleri: "5XXXXXXXXX", "05XXXXXXXXX", "905XXXXXXXXX",
     * "+905XXXXXXXXX". API'nin beklediği tek biçime (ülke kodlu, + işaretsiz,
     * "905XXXXXXXXX") normalize eder; tanınmayan biçimde null döner.
     */
    private function normalizePhone(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            return '90' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '05')) {
            return '90' . substr($digits, 1);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            return $digits;
        }

        return null;
    }
}
