<?php

namespace Modules\Bagisto\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bagisto tarafındaki `Webkul\SaasSync\Http\Middleware\VerifySaasWebhookSignature`
 * ile eşleşen imzalama: gövde tam olarak gönderildiği JSON string'i üzerinden
 * HMAC-SHA256 ile imzalanır (`X-Saas-Signature`). Guzzle'ın `asJson()` ile
 * ürettiği gövde de aynı `json_encode()` varsayılanlarını kullandığı için,
 * imza burada hesaplanan string ile gönderilen gövde birebir eşleşir.
 */
class BagistoSyncClient
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Illuminate\Http\Client\RequestException  yanıt başarısızsa (4xx/5xx).
     */
    public function post(string $path, array $payload): void
    {
        $baseUrl = rtrim((string) config('bagisto.sync.base_url'), '/');
        $secret  = (string) config('bagisto.sync.webhook_secret');

        if ($baseUrl === '' || $secret === '') {
            Log::warning('Bagisto sync: base_url/webhook_secret yapılandırılmamış, olay atlandı.', [
                'path' => $path,
            ]);

            return;
        }

        $body      = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $secret);

        Http::withHeaders(['X-Saas-Signature' => $signature])
            ->asJson()
            ->timeout((int) config('bagisto.sync.timeout', 15))
            ->post($baseUrl.'/'.ltrim($path, '/'), $payload)
            ->throw();
    }
}
