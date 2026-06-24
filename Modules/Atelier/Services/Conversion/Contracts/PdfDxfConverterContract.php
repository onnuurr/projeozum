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
     * Hızlı ön-kontrol: PDF vektör mü, raster (taranmış) mı? DXF üretmez.
     * Yükleme anında raster dosyayı baştan elemek için. Kontrol yapılamıyorsa
     * (servis kapalı / mock) null döner → çağıran akışı engellemez.
     *
     * @return array{kind:string,pages?:int,vector_pages?:int,image_pages?:int,has_grid?:bool}|null
     */
    public function probe(string $pdfAbsolutePath): ?array;
}
