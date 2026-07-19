<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Parça-bazlı analizden (GarmentPartAnalyzerContract) AYRI, bütünsel bir
 * çağrı: tüm giysi görseline bakıp bu ürünü BENZER ürünlerden ayıran en
 * fazla 5 görsel detayı sıralar (ör. "Pink floral embroidery", "Pearl
 * buttons"). Bu liste try-on prompt'unun EN BAŞINA bir kimlik çapası olarak
 * eklenir. Garment başına BİR KEZ çalışır (GarmentScan.identity_summary'de
 * saklanır) — parça sayısından bağımsız, sabit maliyet.
 */
interface GarmentIdentitySummarizerContract
{
    /**
     * @return array{
     *   analysis_version:int,
     *   model:string,
     *   prompt_version:int,
     *   generated_at:string,
     *   highlights:array<int,string>
     * }
     */
    public function summarize(string $garmentImagePath): array;
}
