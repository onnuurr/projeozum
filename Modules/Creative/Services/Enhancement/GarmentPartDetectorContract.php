<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Bir giysi görselindeki parçaların (yaka, cep, etek, kol ucu vb.) konumunu
 * tespit eder — {@see GarmentDetailClassifierContract}'in aksine bu bir
 * SINIFLANDIRMA değil, NESNE TESPİTİ sözleşmesidir: tam ürün fotoğrafı
 * içinde her parçanın koordinatını (bbox) döner.
 *
 * Lisans kararı (bkz. ROADMAP.md Faz G): Ultralytics YOLO iç kullanımda bile
 * Enterprise lisans gerektirdiğinden ve DeepFashion2/Fashionpedia parça
 * seviyesinde ticari kullanıma uygun olmadığından, gerçek sürücü (Faz G.3)
 * torchvision (BSD) + kendi ürün fotoğraflarıyla fine-tune edilmiş bir
 * ağırlık dosyası kullanır. Ağırlık dosyası henüz yoksa (ilk fine-tune
 * çalıştırılmadan önce) {@see NullGarmentPartDetector} bağlanır — bu adım
 * asla giydirme pipeline'ını bozmamalıdır (bkz. GarmentDetailClassifierContract
 * ile aynı graceful-degrade felsefesi).
 */
interface GarmentPartDetectorContract
{
    /**
     * @return array<int,array{label_key:string,label_display:string,bbox:array{x:float,y:float,w:float,h:float},confidence:float,source?:string}>
     *         bbox koordinatları görsel genişlik/yüksekliğine göre 0..1 normalize edilmiştir
     *         (çözünürlükten bağımsız; UI overlay ve kırpma bu yüzden doğrudan kullanabilir).
     *         Opsiyonel 'source' alanı sürücünün kendisini damgalamasına izin verir
     *         (ör. {@see PythonZeroShotGarmentPartDetector} 'zeroshot' yazar) — boşsa
     *         {@see \Modules\Creative\Services\GarmentScanService::normalizeAndNameLabels()}
     *         'auto'ya düşer. 'manual' değeri yalnız manuel etiketleme aracından
     *         (GarmentScanService::addManualAnnotation/updateAnnotation) gelir, bir
     *         dedektör asla kendi 'manual' üretmemelidir.
     */
    public function detect(string $imagePath): array;

    /**
     * Kullanılan model sürümünü döner (ör. "garment_parts_2026_08_01" ya da
     * hiç eğitilmiş model yoksa "null") — raporlama ve detay sayfasında hangi
     * modelin tespit ürettiğini göstermek için.
     */
    public function modelVersion(): string;
}
