<?php

namespace Modules\PatternEngine\Jobs;

use App\Support\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Services\AiExtraction\Contracts\PatternMetadataExtractorContract;
use Modules\PatternEngine\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\PatternEngine\Services\PatternLibraryService;
use Modules\PatternEngine\Services\PatternMetadataRuleEngine;
use Throwable;

/**
 * Kütüphaneye yüklenen kalıp FOTOĞRAFINI arka planda inceler ve taslak kalıbı
 * doldurur (bkz. python/pdf_dxf_service/photo_vectorize.py — ArUco referans
 * marker ile ölçek+düzlem düzeltmesi). ExtractPatternFromPdfJob'ın fotoğraf
 * kaynağı için ikizi: aynı applyExtraction/AI-pilot akışını paylaşır, yalnızca
 * dönüştürücü çağrısı ve kaynak alanı (photo_path) farklıdır.
 */
class ExtractPatternFromPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $patternId, public float $markerMm = 40.0) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(
        PdfDxfConverterContract $converter,
        PatternLibraryService $library,
        PatternMetadataExtractorContract $metadataExtractor,
        PatternMetadataRuleEngine $ruleEngine,
    ): void {
        $pattern = Pattern::find($this->patternId);
        if (! $pattern || ! $pattern->photo_path) {
            return; // kalıp silinmişse veya fotoğraf yoksa sessizce çık
        }

        try {
            $abs = Media::localPath($pattern->photo_path);
            if (! $abs) {
                throw new \RuntimeException(__('patternengine::messages.pattern.photo_missing_on_disk', ['path' => $pattern->photo_path]));
            }
            $result = $converter->convertPhoto($abs, $this->markerMm);
            $library->applyExtraction($pattern, $result);
        } catch (Throwable $e) {
            $library->markExtractionFailed($pattern, $e->getMessage());

            throw $e; // kuyruk yeniden denesin
        }

        // AI metadata PİLOTU (bkz. ExtractPatternFromPdfJob): yalnızca ürün tipi
        // hâlâ belirsizse devreye girer. Fotoğrafın kendisi zaten kapak görseli
        // rolünde — PDF akışının aksine ayrı bir render adımına gerek yok.
        $pattern->refresh();
        if (in_array($pattern->product_type, [null, '', 'belirsiz'], true)) {
            $this->runAiMetadataPilot($pattern, $abs, $library, $metadataExtractor, $ruleEngine);
        }
    }

    private function runAiMetadataPilot(
        Pattern $pattern,
        string $photoAbsolutePath,
        PatternLibraryService $library,
        PatternMetadataExtractorContract $metadataExtractor,
        PatternMetadataRuleEngine $ruleEngine,
    ): void {
        try {
            $envelope = $metadataExtractor->extract($photoAbsolutePath);
            $decided  = $ruleEngine->decide($envelope);
            $library->applyAiMetadata($pattern, $envelope, $decided);
        } catch (Throwable $e) {
            Log::warning('Atelier kalıp (fotoğraf) AI metadata pilotu atlandı.', [
                'pattern_id' => $pattern->id,
                'reason'     => $e->getMessage(),
            ]);
        }
    }
}
