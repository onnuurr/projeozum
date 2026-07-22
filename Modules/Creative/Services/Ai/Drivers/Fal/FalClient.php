<?php

namespace Modules\Creative\Services\Ai\Drivers\Fal;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * fal.ai queue istemcisi: işi gönderir, tamamlanana dek poll eder, sonucu döndürür.
 *
 * @see https://docs.fal.ai/  (queue submit → status → response)
 */
class FalClient
{
    /**
     * @param  array<string,mixed>  $input  Model girdisi (örn. fashn/tryon model_image/garment_image)
     * @param  string|null  $model  Çağrılacak model id'si; null ise creative.ai.fal.model'e düşer
     *                              (örn. FalFluxComposer kendi creative.composition.fal.model'ini verir).
     * @return array<string,mixed>  Tamamlanan işin sonuç JSON'u
     */
    public function run(array $input, ?string $model = null): array
    {
        $key = (string) config('creative.ai.fal.key');
        if ($key === '') {
            throw new RuntimeException('FAL_KEY tanımlı değil.');
        }

        $base    = rtrim((string) config('creative.ai.fal.base_url'), '/');
        $model   = trim($model ?? (string) config('creative.ai.fal.model'), '/');
        $timeout = (int) config('creative.ai.timeout', 240);

        $submit = Http::timeout($timeout)
            ->withHeaders(['Authorization' => "Key {$key}"])
            ->post("{$base}/{$model}", $input);

        if ($submit->failed()) {
            throw new RuntimeException(sprintf(
                'fal submit başarısız (HTTP %d): %s',
                $submit->status(),
                substr($submit->body(), 0, 500),
            ));
        }

        $statusUrl   = $submit->json('status_url');
        $responseUrl = $submit->json('response_url');

        if (! $statusUrl || ! $responseUrl) {
            throw new RuntimeException('fal yanıtında status_url/response_url yok.');
        }

        $this->pollUntilComplete($key, $statusUrl, $timeout);

        $result = Http::timeout($timeout)
            ->withHeaders(['Authorization' => "Key {$key}"])
            ->get($responseUrl);

        if ($result->failed()) {
            throw new RuntimeException(sprintf(
                'fal sonuç alınamadı (HTTP %d): %s',
                $result->status(),
                substr($result->body(), 0, 500),
            ));
        }

        return $result->json() ?? [];
    }

    private function pollUntilComplete(string $key, string $statusUrl, int $timeout): void
    {
        $tries    = max(1, (int) config('creative.ai.fal.poll_tries', 40));
        $interval = max(1, (int) config('creative.ai.fal.poll_interval', 3));

        for ($i = 0; $i < $tries; $i++) {
            $status = Http::timeout($timeout)
                ->withHeaders(['Authorization' => "Key {$key}"])
                ->get($statusUrl);

            $state = $status->json('status');

            if ($state === 'COMPLETED') {
                return;
            }

            if (! in_array($state, ['IN_QUEUE', 'IN_PROGRESS', null], true)) {
                throw new RuntimeException("fal işi beklenmeyen durumda: " . (string) $state);
            }

            sleep($interval);
        }

        throw new RuntimeException('fal işi zaman aşımına uğradı (poll bitti).');
    }
}
