<?php

namespace Modules\Creative\Services;

use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\AiBatchRequest;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiRequestException;
use Modules\Creative\Services\Ai\Support\AiBatchPending;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;

/**
 * Gemini Batch API (%50 indirim) üzerinden görsel üretimi.
 *
 * Akış: servis bir görsel çağrısını defer() içinde yapar. GeminiClient bağlam varsa HTTP çağrısı
 * yapmaz, intercept() ile isteği satır olarak kuyruğa yazar (AiBatchPending → defer null döner, iş
 * beklemeye geçer). Zamanlayıcı (creative:ai-batch, dakikada bir) kuyruktakileri model başına tek
 * batch'te gönderir, bitenleri toplar ve satırdaki resume işini yeniden kuyruğa alır. İş aynı
 * anahtarla yine defer() çağırır; bu kez intercept() hazır yanıtı döndürür.
 *
 * Yalnız görsel çağrıları batch'e gider; analiz çağrıları ucuz olduğu için anlık kalır.
 * Container'da scoped (bağlam yığını kuyruk işi başına sıfırlanır).
 */
class AiBatchService
{
    /** @var array<int,array{key:string,subject:?Model,resume:?array<string,mixed>}> */
    private array $contexts = [];

    /**
     * $fn içindeki görsel çağrısını batch'e yönlendirir. $key null ise (anlık mod) $fn doğrudan çalışır.
     * Sonuç hazır değilse null döner.
     *
     * @template T
     * @param  array{job:class-string,args:array<int,mixed>}|null  $resume  Sonuç gelince kuyruğa alınacak iş
     * @param  Closure():T  $fn
     * @return T|null
     */
    public function defer(?string $key, ?Model $subject, ?array $resume, Closure $fn): mixed
    {
        if ($key === null) {
            return $fn();
        }

        $this->contexts[] = ['key' => $key, 'subject' => $subject, 'resume' => $resume];
        try {
            return $fn();
        } catch (AiBatchPending $e) {
            if ($e->key !== $key) {
                throw $e;
            }

            return null;
        } finally {
            array_pop($this->contexts);
        }
    }

    /**
     * GeminiClient'tan: bağlam varsa hazır yanıtı döndürür ya da isteği kuyruğa yazıp AiBatchPending
     * fırlatır; bağlam yoksa null (normal anlık çağrı yapılır).
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|null
     */
    public function intercept(string $operation, string $model, array $payload): ?array
    {
        $ctx = end($this->contexts);
        if ($ctx === false) {
            return null;
        }

        $row = AiBatchRequest::where('key', $ctx['key'])->first();
        if ($row === null) {
            $path = trim((string) config('creative.ai.gemini.batch.dir', 'creative/ai-batch'), '/') . '/' . sha1($ctx['key']) . '.json';
            AiBatchRequest::disk()->put($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
            AiBatchRequest::create([
                'key'          => $ctx['key'],
                'model'        => $model,
                'operation'    => $operation,
                'status'       => AiBatchRequest::STATUS_QUEUED,
                'payload_path' => $path,
                'subject_type' => $ctx['subject']?->getMorphClass(),
                'subject_id'   => $ctx['subject']?->getKey(),
                'resume'       => $ctx['resume'],
            ]);
            throw new AiBatchPending($ctx['key']);
        }

        if ($row->status === AiBatchRequest::STATUS_FAILED) {
            throw new GeminiRequestException((string) ($row->error ?: GeminiRequestException::FAILED));
        }
        if ($row->status !== AiBatchRequest::STATUS_SUCCEEDED) {
            throw new AiBatchPending($ctx['key']);
        }

        $json = json_decode((string) AiBatchRequest::disk()->get((string) $row->response_path), true);
        if (! is_array($json)) {
            throw new GeminiRequestException(GeminiRequestException::EMPTY_RESPONSE);
        }

        return $json;
    }

    /**
     * Kuyruktaki istekleri model başına toplu gönderir (inline istek gövdesi sınırı: ~20 MB).
     *
     * @return int gönderilen istek sayısı
     */
    public function submitQueued(): int
    {
        $limit = (int) config('creative.ai.gemini.batch.max_inline_bytes', 18_000_000);
        $sent = 0;

        foreach (AiBatchRequest::where('status', AiBatchRequest::STATUS_QUEUED)->orderBy('id')->get()->groupBy('model') as $model => $rows) {
            $chunk = [];
            $size = 0;
            foreach ($rows as $row) {
                $bytes = (int) (AiBatchRequest::disk()->size((string) $row->payload_path) ?: 0);
                if ($chunk !== [] && $size + $bytes > $limit) {
                    $sent += $this->submit((string) $model, $chunk);
                    [$chunk, $size] = [[], 0];
                }
                $chunk[] = $row;
                $size += $bytes;
            }
            if ($chunk !== []) {
                $sent += $this->submit((string) $model, $chunk);
            }
        }

        return $sent;
    }

    /** @param array<int,AiBatchRequest> $rows */
    private function submit(string $model, array $rows): int
    {
        $requests = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) AiBatchRequest::disk()->get((string) $row->payload_path), true);
            if (! is_array($payload)) {
                $this->fail($row, GeminiRequestException::FILE_MISSING);

                continue;
            }
            $requests[] = ['request' => $payload, 'metadata' => ['key' => (string) $row->id]];
        }
        if ($requests === []) {
            return 0;
        }

        try {
            $response = $this->http()->post($this->base() . "/models/{$model}:batchGenerateContent", [
                'batch' => [
                    'display_name' => 'creative-' . $rows[0]->id,
                    'input_config' => ['requests' => ['requests' => $requests]],
                ],
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Gemini batch gönderilemedi; sonraki turda yeniden denenecek.', ['error' => $e->getMessage()]);

            return 0;
        }

        $name = (string) $response->json('name');
        if ($response->failed() || $name === '') {
            $e = GeminiRequestException::fromResponse($response);
            Log::warning('Gemini batch reddedildi.', ['model' => $model, 'http_status' => $response->status(), 'provider_message' => $response->json('error.message')]);
            // Kota / geçici hata: kuyrukta kalsın; kalıcı hata (400, yetki): işler hatayla sonlansın.
            if (in_array($e->reason, [GeminiRequestException::QUOTA, GeminiRequestException::UNAVAILABLE], true)) {
                return 0;
            }
            foreach ($rows as $row) {
                $this->fail($row, $e->reason);
            }
            $this->resume($rows);

            return 0;
        }

        AiBatchRequest::whereIn('id', collect($rows)->pluck('id'))->where('status', AiBatchRequest::STATUS_QUEUED)
            ->update(['status' => AiBatchRequest::STATUS_SUBMITTED, 'batch_name' => $name, 'submitted_at' => now()]);

        return count($requests);
    }

    /**
     * Google'daki batch'lerin durumunu sorar; bitenlerin yanıtlarını dosyaya yazar, maliyetini
     * kaydeder ve bekleyen işleri yeniden kuyruğa alır.
     *
     * @return int tamamlanan istek sayısı
     */
    public function poll(): int
    {
        $done = 0;
        foreach (AiBatchRequest::where('status', AiBatchRequest::STATUS_SUBMITTED)->distinct()->pluck('batch_name') as $name) {
            try {
                $response = $this->http()->get($this->base() . '/' . ltrim((string) $name, '/'));
            } catch (ConnectionException $e) {
                continue;
            }
            if ($response->failed()) {
                Log::warning('Gemini batch durumu okunamadı.', ['batch' => $name, 'http_status' => $response->status()]);

                continue;
            }

            $json = (array) $response->json();
            $state = str_replace(['BATCH_STATE_', 'JOB_STATE_'], '', (string) ($json['metadata']['state'] ?? $json['state'] ?? ''));
            if (! in_array($state, ['SUCCEEDED', 'FAILED', 'CANCELLED', 'EXPIRED'], true)) {
                continue;
            }

            $rows = AiBatchRequest::where('batch_name', $name)->where('status', AiBatchRequest::STATUS_SUBMITTED)->orderBy('id')->get();
            $items = $json['response']['inlinedResponses']['inlinedResponses']
                ?? $json['metadata']['output']['inlinedResponses']['inlinedResponses']
                ?? $json['response']['inlinedResponses']
                ?? [];
            $byKey = [];
            foreach (array_values((array) $items) as $i => $item) {
                $byKey[(string) ($item['metadata']['key'] ?? ($rows[$i]->id ?? ''))] = $item;
            }

            foreach ($rows as $row) {
                $item = $byKey[(string) $row->id] ?? null;
                if ($state === 'SUCCEEDED' && is_array($item['response'] ?? null)) {
                    $this->succeed($row, $item['response']);
                } else {
                    $this->fail($row, $state === 'EXPIRED' ? GeminiRequestException::UNAVAILABLE : $this->reasonFrom($item['error'] ?? null));
                }
                $done++;
            }
            $this->resume($rows->all());
        }

        return $done;
    }

    /** @param array<string,mixed> $response */
    private function succeed(AiBatchRequest $row, array $response): void
    {
        $path = preg_replace('/\.json$/', '.response.json', (string) $row->payload_path);
        AiBatchRequest::disk()->put($path, json_encode($response, JSON_UNESCAPED_SLASHES));

        $payload = (array) json_decode((string) AiBatchRequest::disk()->get((string) $row->payload_path), true);
        $recorder = app(AiUsageRecorder::class);
        $recorder->forSubject($row->subject, fn () => $recorder->recordGemini($row->operation, $row->model,
            ['generationConfig' => $payload['generationConfig'] ?? []], $response, batch: true));

        AiBatchRequest::disk()->delete((string) $row->payload_path);
        $row->update(['status' => AiBatchRequest::STATUS_SUCCEEDED, 'response_path' => $path, 'payload_path' => null, 'completed_at' => now()]);
    }

    private function fail(AiBatchRequest $row, string $reason): void
    {
        $row->update(['status' => AiBatchRequest::STATUS_FAILED, 'error' => $reason, 'completed_at' => now()]);
    }

    /** @param array<string,mixed>|null $error */
    private function reasonFrom(?array $error): string
    {
        return match ((string) ($error['status'] ?? '')) {
            'RESOURCE_EXHAUSTED' => GeminiRequestException::QUOTA,
            'INVALID_ARGUMENT'   => GeminiRequestException::BAD_REQUEST,
            'PERMISSION_DENIED'  => GeminiRequestException::DENIED,
            default              => GeminiRequestException::FAILED,
        };
    }

    /**
     * Biten satırların bekleyen işlerini (tekilleştirerek) yeniden kuyruğa alır. Yalnız Creative
     * işleri kabul edilir (resume DB'den okunur).
     *
     * @param  array<int,AiBatchRequest>  $rows
     */
    private function resume(array $rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $job = (string) ($row->resume['job'] ?? '');
            $args = array_values((array) ($row->resume['args'] ?? []));
            $sig = $job . json_encode($args);
            if ($job === '' || isset($seen[$sig]) || ! str_starts_with($job, 'Modules\\Creative\\Jobs\\')
                || ! class_exists($job) || ! is_subclass_of($job, ShouldQueue::class)) {
                continue;
            }
            $seen[$sig] = true;
            dispatch(new $job(...$args));
        }
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('creative.ai.gemini.api_key');
        if ($key === '') {
            throw new GeminiRequestException(GeminiRequestException::MISSING_KEY);
        }

        return Http::timeout((int) config('creative.ai.timeout', 240))->withHeaders(['x-goog-api-key' => $key]);
    }

    private function base(): string
    {
        return rtrim((string) config('creative.ai.gemini.base_url'), '/');
    }
}
