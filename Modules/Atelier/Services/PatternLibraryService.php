<?php

namespace Modules\Atelier\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Conversion\ConversionResult;

/**
 * Kalıp kütüphanesi yazma işlemleri (Modül C).
 *
 * Geometri DOSYADA yaşar (Seviye 1, yol haritası §9.5): DXF/PDF/önizleme
 * 'public' diskinde tutulur, VT yalnızca yolları + aranabilir metadatayı saklar.
 * Parça ve etiketler her kayıtta tam senkronlanır (küçük N).
 */
class PatternLibraryService
{
    private const DIR = 'atelier/patterns';

    /** @var array<string,string> alan → kabul edilen uzantı doğrulaması üst katmanda */
    private const FILE_FIELDS = [
        'preview_image' => 'preview_image_path',
        'dxf'           => 'dxf_path',
        'pdf'           => 'pdf_path',
    ];

    /**
     * @param  array<string,mixed>            $data   metadata (name, product_type, ...)
     * @param  array<string,UploadedFile|null> $files  preview_image/dxf/pdf
     * @param  array<int,array<string,mixed>> $parts
     * @param  array<int,string>              $tags
     */
    public function create(array $data, array $files, array $parts, array $tags, ?int $createdBy = null): Pattern
    {
        return DB::transaction(function () use ($data, $files, $parts, $tags, $createdBy) {
            $pattern = new Pattern($data);
            $pattern->created_by = $createdBy;
            $this->applyFiles($pattern, $files);
            $pattern->save();

            $this->syncParts($pattern, $parts);
            $this->syncTags($pattern, $tags);

            return $pattern->load('parts', 'tags');
        });
    }

    /**
     * @param  array<string,mixed>            $data
     * @param  array<string,UploadedFile|null> $files
     * @param  array<int,array<string,mixed>> $parts
     * @param  array<int,string>              $tags
     */
    public function update(Pattern $pattern, array $data, array $files, array $parts, array $tags): Pattern
    {
        return DB::transaction(function () use ($pattern, $data, $files, $parts, $tags) {
            $pattern->fill($data);
            $this->applyFiles($pattern, $files);
            $pattern->save();

            $this->syncParts($pattern, $parts);
            $this->syncTags($pattern, $tags);

            return $pattern->load('parts', 'tags');
        });
    }

    /**
     * Yüklenen PDF'ten 'inceleniyor' bir taslak kalıp oluşturur (anında listede görünür).
     * Asıl metadata çıkarımı arka planda ExtractPatternFromPdfJob ile yapılır.
     */
    public function createPdfDraft(UploadedFile $pdf, ?int $createdBy = null): Pattern
    {
        $path = $pdf->store(self::DIR, 'public');

        return Pattern::create([
            'name'              => $this->stem($pdf->getClientOriginalName()),
            'product_type'      => 'belirsiz', // çıkarım sonrası güncellenir
            'status'            => Pattern::STATUS_DRAFT,
            'extraction_status' => Pattern::EXTRACTION_PROCESSING,
            'pdf_path'          => $path,
            'created_by'        => $createdBy,
        ]);
    }

    /**
     * Dönüştürücü çıktısını taslak kalıba uygular: DXF'i saklar, metadatayı ve
     * parçaları yazar, durumu 'done' yapar. (Mapping ConversionPipelineService::approve ile aynı.)
     */
    public function applyExtraction(Pattern $pattern, ConversionResult $result): Pattern
    {
        // DXF üretilemediyse veya kırmızı triyajsa çıkarım anlamlı sonuç vermedi
        // (ör. raster/taranmış ya da desteklenmeyen format). 'done' gibi gösterme;
        // operatöre GERÇEK sebeple 'failed' işaretle (yol haritası §2.4 insan onayı).
        if ($result->dxf === null || $result->dxf === '' || $result->classification === 'red') {
            // Raster otomatik vektörleştirme sonuç vermediyse (çizgi yok) operatör elle
            // izlesin → needs_tracing (tracer). Vektör hattındaki red ise gerçek hata.
            if (($result->metadata['source'] ?? null) === 'raster') {
                $this->markNeedsTracing($pattern);

                return $pattern->fresh();
            }

            $msg = ! empty($result->errors)
                ? implode(' ', $result->errors)
                : 'Vektör kalıp çıkarılamadı (taranmış/desteklenmeyen format olabilir).';
            $this->markExtractionFailed($pattern, $msg);

            return $pattern->fresh();
        }

        return DB::transaction(function () use ($pattern, $result) {
            $meta = $result->metadata;

            $dxfPath = $pattern->dxf_path;
            if ($result->dxf !== null && $result->dxf !== '') {
                $this->deleteFile($pattern->dxf_path);
                $dxfPath = self::DIR . '/' . Str::uuid() . '.dxf';
                Storage::disk('public')->put($dxfPath, $result->dxf);
            }

            $pattern->update([
                'name'               => $meta['name'] ?? $pattern->name,
                'product_type'       => $meta['product_type'] ?? $pattern->product_type,
                'size_range'         => $meta['size_range'] ?? null,
                'vendor'             => $meta['profile'] ?? $pattern->vendor,
                'size_layers'        => $meta['size_layers'] ?? null,
                'color_size_map'     => $meta['color_size_map'] ?? null,
                'measurements'       => $meta['measurements'] ?? null,
                'fabric_usage'       => $meta['fabric_usage'] ?? null,
                'scale_verified'     => (bool) ($meta['scale_verified'] ?? false),
                'scale_deviation_mm' => $meta['scale_deviation_mm'] ?? null,
                'dxf_path'           => $dxfPath,
                'extraction_status'  => Pattern::EXTRACTION_DONE,
                'extraction_error'   => null,
            ]);

            $this->syncParts($pattern, $meta['parts'] ?? []);

            return $pattern->fresh('parts');
        });
    }

    /**
     * İnsan-destekli izleme çıktısını taslağa uygular: DXF'i saklar, parçaları
     * yazar, ölçek doğrulanmış sayar, durumu 'done' yapar. (applyExtraction deseni.)
     *
     * @param  array<string,mixed>             $meta   name, product_type, size_range
     * @param  array<int,array<string,mixed>>  $parts
     */
    public function applyTracedDxf(Pattern $pattern, string $dxf, array $meta, array $parts): Pattern
    {
        return DB::transaction(function () use ($pattern, $dxf, $meta, $parts) {
            $this->deleteFile($pattern->dxf_path);
            $dxfPath = self::DIR . '/' . Str::uuid() . '.dxf';
            Storage::disk('public')->put($dxfPath, $dxf);

            $pattern->update([
                'name'              => $meta['name'] ?? $pattern->name,
                'product_type'      => $meta['product_type'] ?? $pattern->product_type,
                'size_range'        => $meta['size_range'] ?? $pattern->size_range,
                'dxf_path'          => $dxfPath,
                'scale_verified'    => true,
                'extraction_status' => Pattern::EXTRACTION_DONE,
                'extraction_error'  => null,
            ]);

            $this->syncParts($pattern, $parts);

            return $pattern->fresh('parts');
        });
    }

    /**
     * Çıkarım hatasını taslak kalıba işler (operatör görür, siler/yeniden yükler).
     */
    public function markExtractionFailed(Pattern $pattern, string $error): void
    {
        $pattern->update([
            'extraction_status' => Pattern::EXTRACTION_FAILED,
            'extraction_error'  => mb_substr($error, 0, 1000),
        ]);
    }

    /**
     * Taslağı insan-destekli izleme bekler durumuna alır (raster yolu).
     */
    public function markNeedsTracing(Pattern $pattern): void
    {
        $pattern->update([
            'extraction_status' => Pattern::EXTRACTION_NEEDS_TRACING,
            'extraction_error'  => null,
        ]);
    }

    /**
     * Kalıbı ve sakladığı dosyaları kaldırır (soft delete + dosya temizliği).
     */
    public function delete(Pattern $pattern): void
    {
        foreach (self::FILE_FIELDS as $column) {
            $this->deleteFile($pattern->{$column});
        }
        $pattern->delete();
    }

    /**
     * @param  array<string,UploadedFile|null>  $files
     */
    private function applyFiles(Pattern $pattern, array $files): void
    {
        foreach (self::FILE_FIELDS as $field => $column) {
            $file = $files[$field] ?? null;
            if ($file instanceof UploadedFile) {
                $this->deleteFile($pattern->{$column}); // eskisini değiştir
                $pattern->{$column} = $file->store(self::DIR, 'public');
            }
        }
    }

    private function deleteFile(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $parts
     */
    private function syncParts(Pattern $pattern, array $parts): void
    {
        $pattern->parts()->delete();
        foreach ($parts as $part) {
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
    }

    /**
     * @param  array<int,string>  $tags
     */
    private function syncTags(Pattern $pattern, array $tags): void
    {
        $pattern->tags()->delete();
        $seen = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || isset($seen[$tag])) {
                continue;
            }
            $seen[$tag] = true;
            $pattern->tags()->create(['tag' => $tag]);
        }
    }

    /** Dosya adından uzantısız, okunabilir bir ad türetir. */
    private function stem(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return trim($base) !== '' ? $base : 'Kalıp';
    }
}
