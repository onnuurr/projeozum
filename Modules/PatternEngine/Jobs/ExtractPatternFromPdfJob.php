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
 * Kütüphaneye yüklenen PDF'i arka planda inceler ve taslak kalıbı doldurur.
 *
 * Sayısallaştırma onay kuyruğundan AYRI: burada çıktı doğrudan DRAFT bir Pattern'e
 * uygulanır (operatör sonradan düzenler/onaylar). Dönüştürücü servisi yavaş olabilir.
 */
class ExtractPatternFromPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    // Raster otomatik vektörleştirme çok sayfalı taramalarda dakikalar sürebilir.
    public int $timeout = 900;

    public function __construct(public int $patternId) {}

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
        if (! $pattern || ! $pattern->pdf_path) {
            return; // kalıp silinmişse veya PDF yoksa sessizce çık
        }

        try {
            $abs = Media::localPath($pattern->pdf_path);
            if (! $abs) {
                throw new \RuntimeException('Kalıp PDF dosyası diskte bulunamadı: ' . $pattern->pdf_path);
            }
            $result = $converter->convert($abs);
            $library->applyExtraction($pattern, $result);
        } catch (Throwable $e) {
            $library->markExtractionFailed($pattern, $e->getMessage());

            throw $e; // kuyruk yeniden denesin
        }

        // AI metadata PİLOTU: yalnızca profil/genel çıkarım ürün tipini belirleyemediyse
        // (satıcı formatı tanınmadı) devreye girer. Opsiyonel zenginleştirme — hata
        // durumunda ana çıkarımı ASLA bozmaz, sessizce atlanır (kuyruk yeniden denemez).
        $pattern->refresh();
        if (in_array($pattern->product_type, [null, '', 'belirsiz'], true)) {
            $this->runAiMetadataPilot($pattern, $abs, $converter, $library, $metadataExtractor, $ruleEngine);
        }
    }

    private function runAiMetadataPilot(
        Pattern $pattern,
        string $pdfAbsolutePath,
        PdfDxfConverterContract $converter,
        PatternLibraryService $library,
        PatternMetadataExtractorContract $metadataExtractor,
        PatternMetadataRuleEngine $ruleEngine,
    ): void {
        $tmp = null;

        try {
            $png = $converter->renderPage($pdfAbsolutePath, 0, 150);
            $tmp = tempnam(sys_get_temp_dir(), 'atelier_ai_cover_') . '.png';
            file_put_contents($tmp, $png);

            $envelope = $metadataExtractor->extract($tmp);
            $decided  = $ruleEngine->decide($envelope);
            $library->applyAiMetadata($pattern, $envelope, $decided);
        } catch (Throwable $e) {
            Log::warning('Atelier kalıp AI metadata pilotu atlandı.', [
                'pattern_id' => $pattern->id,
                'reason'     => $e->getMessage(),
            ]);
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
