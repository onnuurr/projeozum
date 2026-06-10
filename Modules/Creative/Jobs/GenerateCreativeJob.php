<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Services\CreativeRenderService;
use Throwable;

class GenerateCreativeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    // AI sahne pipeline (Gemini compose + fal poll) uzun sürebilir.
    public int $timeout = 300;

    public function __construct(public int $assetId) {}

    public function handle(CreativeRenderService $service): void
    {
        $asset = CreativeAsset::find($this->assetId);

        if (! $asset) {
            return;
        }

        $asset->update(['status' => CreativeAsset::STATUS_PROCESSING, 'error' => null]);

        try {
            $service->generate($asset);
        } catch (Throwable $e) {
            $this->fail($asset, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $asset = CreativeAsset::find($this->assetId);
        if ($asset) {
            $this->fail($asset, $e);
        }
    }

    private function fail(CreativeAsset $asset, Throwable $e): void
    {
        Log::warning('Creative render başarısız', [
            'asset_id' => $asset->id,
            'error'    => $e->getMessage(),
        ]);

        $asset->update([
            'status' => CreativeAsset::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
