<?php

namespace Modules\Atelier\Services\Conversion;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Throwable;

/**
 * PDF→DXF sayısallaştırma hattı (Kol 1).
 *
 * Yükle → kuyrukla işle → insan onay kuyruğu → onayla (kalıba dönüştür) / reddet.
 * Otomatik onay YOK; sınıflandırma yalnızca operatöre triyaj rengi verir (§2.4).
 */
class ConversionPipelineService
{
    public function __construct(private PdfDxfConverterContract $converter) {}

    private function disk(): string
    {
        return (string) config('atelier.conversion.disk', 'public');
    }

    /**
     * Yüklenen PDF'i saklar ve beklemede bir dönüştürme işi oluşturur.
     */
    public function submit(UploadedFile $pdf, ?int $createdBy = null): ConversionJob
    {
        $path = $pdf->store((string) config('atelier.conversion.pdf_dir', 'atelier/conversions/pdf'), $this->disk());

        return ConversionJob::create([
            'source_pdf_path' => $path,
            'status'          => ConversionJob::STATUS_PENDING,
            'created_by'      => $createdBy,
        ]);
    }

    /**
     * İşi dönüştürücüye verir; çıktıyı saklayıp onay kuyruğuna (needs_review) taşır.
     * Hata olursa işi 'failed'/kırmızı işaretler — kuyruk işçisi bunu yutmaz.
     */
    public function process(ConversionJob $job): ConversionJob
    {
        $job->update(['status' => ConversionJob::STATUS_PROCESSING]);

        try {
            $abs = Media::localPath($job->source_pdf_path, $this->disk());
            if (! $abs) {
                throw new \RuntimeException('Kaynak PDF diskte bulunamadı: ' . $job->source_pdf_path);
            }
            $result = $this->converter->convert($abs);

            $dxfPath = null;
            if ($result->dxf !== null && $result->dxf !== '') {
                $dxfPath = trim((string) config('atelier.conversion.dxf_dir', 'atelier/conversions/dxf'), '/')
                    . '/' . Str::uuid() . '.dxf';
                Storage::disk($this->disk())->put($dxfPath, $result->dxf);
            }

            $job->update([
                'output_dxf_path'  => $dxfPath,
                'classification'   => $result->classification,
                'confidence_score' => $result->confidence,
                'error_report'     => ['metadata' => $result->metadata, 'errors' => $result->errors],
                'status'           => ConversionJob::STATUS_NEEDS_REVIEW,
            ]);
        } catch (Throwable $e) {
            $job->update([
                'classification' => ConversionJob::CLASS_RED,
                'error_report'   => ['errors' => [$e->getMessage()]],
                'status'         => ConversionJob::STATUS_FAILED,
            ]);

            throw $e;
        }

        return $job->fresh();
    }

    /**
     * Operatör onayı: işten bir Pattern kaydı türetir ve ikisini bağlar.
     */
    public function approve(ConversionJob $job, ?int $reviewerId = null): Pattern
    {
        $meta = $job->error_report['metadata'] ?? [];

        return DB::transaction(function () use ($job, $meta, $reviewerId) {
            $pattern = Pattern::create([
                'name'               => $meta['name'] ?? pathinfo((string) $job->source_pdf_path, PATHINFO_FILENAME),
                'product_type'       => $meta['product_type'] ?? 'belirsiz',
                'size_range'         => $meta['size_range'] ?? null,
                'vendor'             => $meta['profile'] ?? null,
                'size_layers'        => $meta['size_layers'] ?? null,
                'color_size_map'     => $meta['color_size_map'] ?? null,
                'measurements'       => $meta['measurements'] ?? null,
                'fabric_usage'       => $meta['fabric_usage'] ?? null,
                'scale_verified'     => (bool) ($meta['scale_verified'] ?? false),
                'scale_deviation_mm' => $meta['scale_deviation_mm'] ?? null,
                'dxf_path'           => $job->output_dxf_path,
                'pdf_path'           => $job->source_pdf_path,
                'status'             => Pattern::STATUS_APPROVED,
                'created_by'         => $reviewerId,
            ]);

            foreach (($meta['parts'] ?? []) as $part) {
                $name = trim((string) ($part['part_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $pattern->parts()->create([
                    'part_name'  => $name,
                    'quantity'   => max(1, (int) ($part['quantity'] ?? 1)),
                    'size_range' => $part['size_range'] ?? null,
                ]);
            }

            $job->update([
                'status'      => ConversionJob::STATUS_APPROVED,
                'pattern_id'  => $pattern->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return $pattern->load('parts');
        });
    }

    /**
     * Operatör reddi: iş kapanır, kalıp üretilmez.
     */
    public function reject(ConversionJob $job, ?int $reviewerId = null): ConversionJob
    {
        $job->update([
            'status'      => ConversionJob::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return $job->fresh();
    }
}
