<?php

namespace Modules\Atelier\Jobs;

use App\Support\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\PatternLibraryService;
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

    public function handle(PdfDxfConverterContract $converter, PatternLibraryService $library): void
    {
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
    }
}
