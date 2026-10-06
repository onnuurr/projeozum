<?php

namespace Modules\Creative\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Creative\Models\AiBatchRequest;
use Modules\Creative\Services\AiBatchService;

/**
 * Gemini Batch API kuyruğunu ilerletir: kuyruktaki görsel isteklerini toplu gönderir, biten
 * batch'leri toplar ve bekleyen işleri (mokap baz/giydirme) yeniden kuyruğa alır.
 * Zamanlayıcı dakikada bir çalıştırır (routes/console.php). Kendisi yeni istek ÜRETMEZ; yalnız
 * daha önce kuyruğa yazılmış (maliyeti onaylanmış) istekleri gönderir.
 *
 *   php artisan creative:ai-batch            # gönder + topla
 *   php artisan creative:ai-batch --status   # yalnız durum özeti
 */
class AiBatchCommand extends Command
{
    protected $signature = 'creative:ai-batch {--status : Yalnız kuyruk özetini göster}';

    protected $description = 'Gemini Batch API: kuyruktaki görsel isteklerini gönder, bitenleri topla, işleri sürdür';

    public function handle(AiBatchService $batch): int
    {
        // Zamanlayıcı dakikada bir çağırır; migration çalışmadan önce hata üretmesin.
        if (! Schema::hasTable('creative_ai_batch_requests')) {
            return self::SUCCESS;
        }

        if (! $this->option('status')) {
            $sent = $batch->submitQueued();
            $done = $batch->poll();
            if ($sent + $done > 0) {
                $this->info("Gönderilen: {$sent}, tamamlanan: {$done}");
            }
        }

        if ($this->option('status')) {
            $rows = AiBatchRequest::query()->selectRaw('status, count(*) as n, min(created_at) as oldest')->groupBy('status')->get();
            $this->table(['Durum', 'Adet', 'En eski'], $rows->map(fn ($r) => [$r->status, $r->n, $r->oldest])->all());
        }

        return self::SUCCESS;
    }
}
