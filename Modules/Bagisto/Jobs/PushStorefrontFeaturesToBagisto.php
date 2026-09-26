<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Bagisto\Services\StorefrontFeatureService;
use Throwable;

/**
 * Bayi portalı özellik anahtarlarının tamamını mağazaya gönderir. Değerler handle() anında okunur;
 * art arda kayıtlarda son durum gider (ShouldBeUnique: kuyrukta bekleyen tek kopya yeter).
 */
class PushStorefrontFeaturesToBagisto implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public int $uniqueFor = 60;

    public function handle(StorefrontFeatureService $features, BagistoSyncClient $client): void
    {
        $client->post('api/saas-sync/features', ['features' => $features->values()]);

        $features->markSynced();
    }

    public function failed(?Throwable $e): void
    {
        app(StorefrontFeatureService::class)->markFailed($e?->getMessage() ?? 'Bilinmeyen hata');
    }
}
