<?php

namespace Modules\Atelier\Services\Conversion\Contracts;

use Modules\Atelier\Services\Conversion\ConversionResult;

/**
 * PDF→DXF dönüştürücü soyutlaması.
 *
 * Gerçek dönüşüm ayrı bir Python/FastAPI servisinde (PyMuPDF + ezdxf); bu
 * sözleşme Laravel tarafını ondan ayırır. Sürücü config ile değişir (http|mock).
 */
interface PdfDxfConverterContract
{
    /**
     * Yerel bir PDF dosyasını DXF'e çevirir ve sınıflandırma/metadata döndürür.
     *
     * @param  string  $pdfAbsolutePath  dönüştürülecek PDF'in mutlak yolu
     */
    public function convert(string $pdfAbsolutePath): ConversionResult;

    /**
     * Yerel bir kalıp FOTOĞRAFINI DXF'e çevirir. Fotoğrafta bilinen boyutlu bir
     * ArUco referans marker (DICT_4X4_50) kalıp kağıdıyla aynı düzlemde görünmeli —
     * yoksa 'red' + operatöre marker'lı yeniden çekim talimatı döner.
     *
     * @param  string  $photoAbsolutePath  dönüştürülecek fotoğrafın mutlak yolu
     * @param  float   $markerMm           referans marker'ın gerçek kenar uzunluğu (mm)
     */
    public function convertPhoto(string $photoAbsolutePath, float $markerMm = 40.0): ConversionResult;

    /**
     * Hızlı ön-kontrol: PDF vektör mü, raster (taranmış) mı? DXF üretmez.
     * Yükleme anında raster dosyayı baştan elemek için. Kontrol yapılamıyorsa
     * (servis kapalı / mock) null döner → çağıran akışı engellemez.
     *
     * @return array{kind:string,pages?:int,vector_pages?:int,image_pages?:int,has_grid?:bool}|null
     */
    public function probe(string $pdfAbsolutePath): ?array;

    /** PDF sayfasını PNG'ye render eder (tuval backdrop). PNG bytes döner. */
    public function renderPage(string $pdfAbsolutePath, int $page, int $dpi = 200): string;

    /**
     * mm cinsinden poligonları (insan-destekli izleme) DXF'e çevirir.
     *
     * @param  array<int,array{role:string,points:array<int,array{0:float,1:float}>,closed:bool}>  $polylines
     */
    public function buildDxf(array $polylines): string;
}
