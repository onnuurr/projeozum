<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Giysi detay görseli (yaka/düğme/kol ucu vb.) otomatik etiket önerisi sözleşmesi.
 *
 * Kullanıcının try-on ekranında yüklediği detay fotoğraflarının neyi gösterdiğini
 * yerel bir ML modeliyle (Gemini/bulut çağrısı OLMADAN) tahmin eder. Sonuç yalnız
 * bir ÖNERİDİR — kullanıcının elle girdiği/gireceği etiketi asla ezmez, boşsa
 * doldurmak için kullanılır. Devre dışıysa veya başarısız olursa boş dizi döner
 * (graceful degrade) — bu adım asla giydirme pipeline'ını bozmamalıdır.
 */
interface GarmentDetailClassifierContract
{
    /**
     * @param  array<int,string>  $imagePaths  Sınıflandırılacak görsellerin mutlak yolları.
     * @return array<string,array<int,array{key:string,display:string,score:float}>>
     *         Girdi yoluna göre anahtarlanmış, skora göre sıralı aday etiket listesi
     *         (başarısızlık/eşik altı durumunda boş dizi).
     */
    public function classify(array $imagePaths): array;
}
