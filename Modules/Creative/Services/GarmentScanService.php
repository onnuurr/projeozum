<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creative\Models\GarmentLabel;
use Modules\Creative\Models\GarmentScan;
use Modules\Creative\Services\Ai\Contracts\GarmentIdentitySummarizerContract;
use Modules\Creative\Services\Ai\Contracts\GarmentPartAnalyzerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\GarmentPartDetectorContract;
use Modules\Creative\Services\Enhancement\PythonGarmentPartDetector;
use Modules\Creative\Services\Enhancement\PythonZeroShotGarmentPartDetector;
use RuntimeException;
use Throwable;

/**
 * Giysi parça tespiti orkestratörü: bir giysi KAYNAK görselini içerik
 * hash'ine göre tarar (aynı görsel birden çok pozda kullanılıyorsa yeniden
 * taramaz), tespit edilen her parça adını {@see GarmentLabel} sözlüğünde
 * bulur/oluşturur (otomatik isimlendirme), her tespit için KALICI bir crop
 * üretir ve (açıksa) Gemini Vision ile analiz eder (bkz. ROADMAP.md Faz G.5
 * "Garment Identity Preservation"). Yüksek öncelikli tespitler Gemini
 * try-on'a ek referans olarak beslenir (bkz. cropsForTryOn —
 * ProductOnModelService::generate bunu GarmentIdentityRuleEngine'den geçirip
 * manuel yüklenen detay görselleriyle birleştirir).
 *
 * Hiçbir metodu istisna fırlatarak giydirme pipeline'ını bozmaz: tespit/analiz
 * sürücüleri zaten graceful-degrade'dir, burada da etiket kaydı/kırpma/analiz
 * başarısız olursa sessizce atlanır (crop_path/analysis null kalır).
 */
class GarmentScanService
{
    public function __construct(
        private GarmentPartDetectorContract $detector,
        private GarmentPartAnalyzerContract $analyzer,
        private GarmentIdentitySummarizerContract $identitySummarizer,
    ) {}

    /**
     * Bir giysi görselini tarar (ya da daha önce aynı içerikle taranmışsa
     * o kaydı döner). Tespit adımı asla istisna fırlatmaz — başarısız olursa
     * scan status=failed olarak işaretlenir, detections boş kalır.
     *
     * $garmentPath genelde ProductOnModelService::generate() içindeki bir
     * GEÇİCİ dosyadır (prepareGarmentImage çıktısı) ve üretim biter bitmez
     * silinir — bu yüzden ilk taramada içerik, tespit + manuel etiketleme
     * aracının ve ileride eğitim komutunun okuyabileceği KALICI bir kopyaya
     * (creative.disk, garment-scans/{hash}.ext) yazılır; source_path bu
     * kalıcı göreli yolu tutar, ham geçici yolu DEĞİL. Her tespit için crop da
     * aynı şekilde KALICI yazılır (garment-scans/{hash}/{detection_id}.png) —
     * hem cropsForTryOn'un her generate() çağrısında yeniden kırpmasını önler
     * hem analiz sonucunu somut bir dosyaya bağlar.
     */
    public function scan(string $garmentPath): GarmentScan
    {
        $hash = @sha1_file($garmentPath);
        if ($hash === false) {
            throw new RuntimeException('Giysi görseli taramak için okunamadı: '.$garmentPath);
        }

        $scan = GarmentScan::firstOrCreate(
            ['image_hash' => $hash],
            ['status' => GarmentScan::STATUS_QUEUED],
        );

        if (! $scan->source_path) {
            try {
                $scan->update(['source_path' => $this->persistSourceCopy($garmentPath, $hash)]);
            } catch (Throwable $e) {
                Log::warning('Creative giysi kaynak görseli kalıcı kopyaya yazılamadı.', ['reason' => $e->getMessage()]);
                $scan->update(['status' => GarmentScan::STATUS_FAILED, 'error' => $e->getMessage()]);

                return $scan->fresh();
            }
        }

        // driver='null' bir 'done' kaydı ASLA gerçek bir tarama değildir — dedektör
        // o an kapalıydı/ağırlık yoktu diye atlanmıştı (bkz. NullGarmentPartDetector).
        // Bu yüzden zero_shot/fine-tune sonradan açılırsa aynı görsel (hash eşleşse
        // bile) SESSİZCE eski boş sonuca kilitlenmesin diye burada yeniden taranır —
        // gerçek bir dedektörle üretilmiş 'done' kayıtları (driver='python'|'zeroshot')
        // hâlâ olduğu gibi cache'lenir.
        $isStaleNullScan = $scan->status === GarmentScan::STATUS_DONE
            && $scan->driver === 'null'
            && $this->resolveDriverName() !== 'null';

        if ($scan->status === GarmentScan::STATUS_DONE && ! $isStaleNullScan) {
            return $scan;
        }

        $startedAt = microtime(true);

        try {
            $named = $this->normalizeAndNameLabels($this->detector->detect($garmentPath));
            $enriched = $this->enrichWithCropsAndAnalysis($garmentPath, $hash, $named);

            $scan->update([
                'driver' => $this->resolveDriverName(),
                'model_version' => $this->detector->modelVersion(),
                'status' => GarmentScan::STATUS_DONE,
                'detections' => $enriched,
                'identity_summary' => $this->maybeSummarizeIdentity($garmentPath),
                'error' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('Creative giysi parça taraması başarısız.', ['reason' => $e->getMessage()]);
            $scan->update(['status' => GarmentScan::STATUS_FAILED, 'error' => $e->getMessage()]);
        }

        return $scan->fresh();
    }

    /**
     * Giysi görselinin baytlarını creative.disk üzerinde hash'e göre
     * deterministik, kalıcı bir yola yazar (idempotent — aynı hash için
     * tekrar çağrılırsa mevcut dosyanın üstüne aynı bayt'lar yazılır).
     */
    private function persistSourceCopy(string $garmentPath, string $hash): string
    {
        $bytes = (string) file_get_contents($garmentPath);
        $ext = strtolower(pathinfo($garmentPath, PATHINFO_EXTENSION)) ?: 'jpg';
        $rel = sprintf('garment-scans/%s.%s', $hash, $ext);

        if (! Storage::disk(config('creative.disk', 'public'))->put($rel, $bytes)) {
            throw new RuntimeException("Giysi kaynak görseli diske yazılamadı: {$rel}");
        }

        return $rel;
    }

    /**
     * Bütünsel "ayırt edici özellik" özeti — ayrı opsiyonel bayrak
     * (identity_summary_enabled), garment başına BİR KEZ (scan() içinde,
     * manuel annotation'larda TEKRAR çağrılmaz).
     */
    private function maybeSummarizeIdentity(string $garmentPath): ?array
    {
        if (! config('creative.garment_detection.analysis.identity_summary_enabled')) {
            return null;
        }

        return $this->identitySummarizer->summarize($garmentPath);
    }

    /**
     * Taramanın yüksek güvenli, KENDİSİ otomatik tespit edilmiş (source=auto)
     * parçalarından — try-on formunda zaten manuel yüklenmiş bir detay
     * görseliyle (aynı etiketle) çakışmayanlardan — en fazla $maxAutoCrops
     * tanesinin KALICI crop'unu (bkz. scan()) yerel yola çözer.
     *
     * @param  array<int,array{path:string,label:?string}>  $manualExtras  Formdan
     *                                                                     zaten yüklenmiş detay görselleri — aynı etiketli bir otomatik
     *                                                                     tespit varsa manuel yükleme ÖNCELİKLİDİR, tekrar eklenmez.
     * @return array<int,array{path:string,label:string,label_key:string,default_priority:string,analysis:?array}>
     *                                                                                                             GarmentIdentityRuleEngine::directivesFor()'un beklediği zenginleştirilmiş şekil.
     */
    public function cropsForTryOn(GarmentScan $scan, array $manualExtras, int $maxAutoCrops, float $minConfidence): array
    {
        if ($maxAutoCrops <= 0) {
            return [];
        }

        $usedLabels = collect($manualExtras)
            ->map(fn (array $e) => mb_strtolower(trim((string) ($e['label'] ?? ''))))
            ->filter()
            ->all();

        // 'auto' (fine-tune model, Faz G.4) VE 'manual' (etiketleme aracında elle
        // çizilmiş YA DA bir insan tarafından Onayla'nan eski zero-shot önerisi —
        // ikisi de aynı source='manual' değerini taşır, updateAnnotation ayrım
        // yapmıyor) güvenilir sayılır. 'zeroshot' (henüz onaylanmamış AI önerisi)
        // asla buraya girmez — insan onayından geçmeden Gemini prompt'una sızmaz.
        $candidates = collect($scan->detections ?? [])
            ->filter(fn (array $d) => in_array($d['source'] ?? 'auto', ['auto', 'manual'], true))
            ->filter(fn (array $d) => (float) ($d['confidence'] ?? 0) >= $minConfidence)
            ->filter(fn (array $d) => ! empty($d['crop_path']))
            ->filter(fn (array $d) => ! in_array(mb_strtolower(trim((string) ($d['label_display'] ?? ''))), $usedLabels, true))
            ->sortByDesc('confidence')
            ->unique('label_key')
            ->take($maxAutoCrops);

        if ($candidates->isEmpty()) {
            return [];
        }

        $labelsByKey = GarmentLabel::query()
            ->whereIn('key', $candidates->pluck('label_key')->filter()->unique())
            ->get()
            ->keyBy('key');

        return $candidates
            ->map(function (array $d) use ($labelsByKey) {
                $localPath = Media::localPath($d['crop_path']);
                if (! $localPath) {
                    return null;
                }

                $label = $labelsByKey->get($d['label_key']);

                return [
                    'path' => $localPath,
                    'label' => $d['label_display'],
                    'label_key' => $d['label_key'],
                    'default_priority' => $label?->default_priority ?? GarmentLabel::PRIORITY_MEDIUM,
                    'analysis' => $d['analysis'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Her tespit için kaynak görseli BİR KEZ açıp sırayla crop+persist+
     * (açıksa) analiz uygular — scan()'in toplu (batch) yolu.
     *
     * @param  array<int,array<string,mixed>>  $detections
     * @return array<int,array<string,mixed>>
     */
    private function enrichWithCropsAndAnalysis(string $sourcePath, string $hash, array $detections): array
    {
        if ($detections === []) {
            return [];
        }

        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($source === false) {
            // Crop/analiz atlanır; bbox/etiket bilgisi yine de korunur.
            return $detections;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $enriched = array_map(
            fn (array $d) => $this->enrichOne($source, $width, $height, $hash, $d),
            $detections,
        );

        imagedestroy($source);

        return $enriched;
    }

    /**
     * Manuel etiketleme aracının (Faz G.2) TEK bir kutusu için kaynak görseli
     * scan->source_path'ten yeniden açıp aynı crop+analiz işlemini uygular.
     *
     * @param  array<string,mixed>  $detection
     * @return array<string,mixed>
     */
    private function cropAndAnalyzeSingle(GarmentScan $scan, array $detection): array
    {
        $localPath = $scan->source_path ? Media::localPath($scan->source_path) : null;
        if (! $localPath || ! is_file($localPath)) {
            return $detection;
        }

        $source = @imagecreatefromstring((string) file_get_contents($localPath));
        if ($source === false) {
            return $detection;
        }

        $enriched = $this->enrichOne($source, imagesx($source), imagesy($source), $scan->image_hash, $detection);
        imagedestroy($source);

        return $enriched;
    }

    /**
     * @param  \GdImage  $source
     * @param  array<string,mixed>  $detection
     * @return array<string,mixed>
     */
    private function enrichOne($source, int $width, int $height, string $hash, array $detection): array
    {
        $cropped = $this->cropRegion($source, $width, $height, $detection['bbox'] ?? null);
        if ($cropped === null) {
            return $detection;
        }

        $minSide = (int) config('creative.garment_detection.analysis.min_crop_side_px', 768);
        $bytes = $this->encodePng($cropped, $minSide);
        imagedestroy($cropped);

        if ($bytes === null) {
            return $detection;
        }

        $detectionId = (string) ($detection['id'] ?? Str::uuid());
        $rel = sprintf('garment-scans/%s/%s.png', $hash, $detectionId);

        if (! Storage::disk(config('creative.disk', 'public'))->put($rel, $bytes)) {
            Log::warning('Creative giysi parça kırpması diske yazılamadı.', ['detection_id' => $detectionId, 'path' => $rel]);

            return $detection;
        }

        $detection['crop_path'] = $rel;
        $detection['crop_hash'] = 'sha256:'.hash('sha256', $bytes);

        if (config('creative.garment_detection.analysis.enabled')) {
            $detection['analysis'] = $this->analyzeCropBytes($bytes, $detection);
        }

        return $detection;
    }

    /**
     * @param  array<string,mixed>  $detection
     */
    private function analyzeCropBytes(string $bytes, array $detection): ?array
    {
        $labelKey = (string) ($detection['label_key'] ?? '');
        if ($labelKey === '') {
            return null;
        }

        $tempPath = ImageFile::temp($bytes, 'png');

        try {
            $label = GarmentLabel::where('key', $labelKey)->first();
            $category = $label?->preservation_category ?? GarmentLabel::CATEGORY_APPEARANCE;

            return $this->analyzer->analyze($tempPath, $labelKey, (string) ($detection['label_display'] ?? $labelKey), $category);
        } finally {
            ImageFile::delete([$tempPath]);
        }
    }

    /**
     * bbox (0..1 normalize) + %15 pay ile kaynak görselden bir bölge kırpar.
     *
     * @param  \GdImage  $source
     * @param  array{x:float,y:float,w:float,h:float}|null  $bbox
     * @return \GdImage|null
     */
    private function cropRegion($source, int $width, int $height, ?array $bbox)
    {
        if (! is_array($bbox)) {
            return null;
        }

        $boxX = (float) ($bbox['x'] ?? 0) * $width;
        $boxY = (float) ($bbox['y'] ?? 0) * $height;
        $boxW = (float) ($bbox['w'] ?? 0) * $width;
        $boxH = (float) ($bbox['h'] ?? 0) * $height;
        if ($boxW <= 0 || $boxH <= 0) {
            return null;
        }

        // Kırpılan görsel Gemini'ye "yakın çekim" olarak gidiyor; kutunun tam
        // sınırında kesmek yerine %15 pay bırakmak parçanın çevresini de gösterir.
        $padX = $boxW * 0.15;
        $padY = $boxH * 0.15;

        $cropX = max(0, (int) round($boxX - $padX));
        $cropY = max(0, (int) round($boxY - $padY));
        $cropW = min($width - $cropX, (int) round($boxW + 2 * $padX));
        $cropH = min($height - $cropY, (int) round($boxH + 2 * $padY));
        if ($cropW <= 0 || $cropH <= 0) {
            return null;
        }

        $cropped = imagecrop($source, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);

        return $cropped === false ? null : $cropped;
    }

    /**
     * Crop'u PNG'ye kodlar; en uzun kenarı $minSide'dan küçükse kaliteli
     * (bicubic) interpolasyonla büyütür — Gemini'nin küçük donanım
     * detaylarını (düğme vb.) net okuyabilmesi için (bkz. ROADMAP.md Faz G.5,
     * madde 5 — 3 sabit çözünürlük yerine uyarlamalı tek çözünürlük kararı).
     *
     * @param  \GdImage  $cropped
     */
    private function encodePng($cropped, int $minSide): ?string
    {
        $target = $cropped;
        $longest = max(imagesx($cropped), imagesy($cropped));

        if ($minSide > 0 && $longest > 0 && $longest < $minSide) {
            $scale = $minSide / $longest;
            $newW = max(1, (int) round(imagesx($cropped) * $scale));
            $newH = max(1, (int) round(imagesy($cropped) * $scale));
            $scaled = imagescale($cropped, $newW, $newH, IMG_BICUBIC);
            if ($scaled !== false) {
                $target = $scaled;
            }
        }

        ob_start();
        imagepng($target);
        $bytes = ob_get_clean();

        if ($target !== $cropped) {
            imagedestroy($target);
        }

        return ($bytes === false || $bytes === '') ? null : $bytes;
    }

    /**
     * Manuel etiketleme aracından (Faz G.2) yeni bir kutu ekler — bootstrap veri
     * toplama ve mevcut otomatik tespitleri düzeltme mekanizmasının temeli.
     * Diğer tespitler gibi crop+persist+(açıksa) analiz alır.
     *
     * @param  array{x:float,y:float,w:float,h:float}  $bbox
     * @return array<string,mixed>
     */
    public function addManualAnnotation(GarmentScan $scan, array $bbox, string $labelText): array
    {
        $label = $this->resolveLabel($labelText);
        $entry = [
            'id' => (string) Str::uuid(),
            'label_key' => $label->key,
            'label_display' => $label->display,
            'bbox' => $bbox,
            'confidence' => 1.0,
            'source' => 'manual',
        ];
        $entry = $this->cropAndAnalyzeSingle($scan, $entry);

        $detections = (array) ($scan->detections ?? []);
        $detections[] = $entry;
        $scan->update(['detections' => $detections, 'status' => GarmentScan::STATUS_DONE]);

        return $entry;
    }

    /**
     * Var olan bir kutuyu (otomatik ya da manuel) düzeltir — düzeltilen kutu
     * insan onayı gördüğü için source='manual'a geçer (eğitim verisi kalitesi
     * için: bir insanın düzelttiği kutu her zaman güvenilir kabul edilir).
     * Yeni bbox/etiketle crop+analiz YENİDEN üretilir.
     *
     * @param  array{x:float,y:float,w:float,h:float}  $bbox
     * @return array<string,mixed>|null
     */
    public function updateAnnotation(GarmentScan $scan, string $annotationId, array $bbox, string $labelText): ?array
    {
        $label = $this->resolveLabel($labelText);
        $detections = (array) ($scan->detections ?? []);
        $updated = null;

        foreach ($detections as &$d) {
            if (($d['id'] ?? null) === $annotationId) {
                $d['bbox'] = $bbox;
                $d['label_key'] = $label->key;
                $d['label_display'] = $label->display;
                $d['source'] = 'manual';
                $d['confidence'] = 1.0;
                $d = $this->cropAndAnalyzeSingle($scan, $d);
                $updated = $d;
                break;
            }
        }
        unset($d);

        if ($updated === null) {
            return null;
        }

        $scan->update(['detections' => $detections]);

        return $updated;
    }

    public function removeAnnotation(GarmentScan $scan, string $annotationId): void
    {
        $detections = collect((array) ($scan->detections ?? []))
            ->reject(fn (array $d) => ($d['id'] ?? null) === $annotationId)
            ->values()
            ->all();

        $scan->update(['detections' => $detections]);
    }

    private function resolveLabel(string $rawLabel): GarmentLabel
    {
        $key = Str::slug(trim($rawLabel), '_');
        if ($key === '') {
            $key = 'etiket_'.Str::random(6);
        }

        return $this->resolveLabelByKey($key, trim($rawLabel) ?: $key);
    }

    /**
     * Her tespitin label_key'ini {@see GarmentLabel} sözlüğünde bulur/oluşturur
     * (otomatik isimlendirme) ve detections dizisini kanonik display adıyla
     * normalize eder. crop_path/crop_hash/analysis burada DOLDURULMAZ —
     * scan() ayrıca enrichWithCropsAndAnalysis() ile ekler.
     *
     * @param  array<int,array{label_key:string,label_display:string,bbox:array{x:float,y:float,w:float,h:float},confidence:float}>  $detections
     * @return array<int,array{id:string,label_key:string,label_display:string,bbox:array,confidence:float,source:string}>
     */
    private function normalizeAndNameLabels(array $detections): array
    {
        $normalized = [];

        foreach ($detections as $d) {
            $key = Str::slug((string) ($d['label_key'] ?? ''), '_');
            if ($key === '') {
                continue;
            }

            $label = $this->resolveLabelByKey($key, (string) ($d['label_display'] ?? $key));

            $normalized[] = [
                'id' => (string) Str::uuid(),
                'label_key' => $label->key,
                'label_display' => $label->display,
                'bbox' => $d['bbox'] ?? null,
                'confidence' => (float) ($d['confidence'] ?? 0),
                // Sürücü kendi kaynağını damgalayabilir (ör. PythonZeroShotGarmentPartDetector
                // 'zeroshot' yazar) — cropsForTryOn/creative:train-garment-detector bu
                // ayrıma göre filtreler (bkz. GarmentPartDetectorContract).
                'source' => $d['source'] ?? 'auto',
            ];
        }

        return $normalized;
    }

    /**
     * Bir etiketi anahtarına göre bulur/oluşturur. İLK KEZ oluşturulan bir
     * etiket, config('creative.garment_detection.label_defaults')'ta
     * tanımlıysa (ör. "dugme" → hardware/critical) o sabit korunma
     * sınıfı/önceliğiyle açılır — tanımlı değilse DB kolon varsayılanına
     * (appearance/medium) düşer (bkz. ROADMAP.md Faz G.5).
     */
    private function resolveLabelByKey(string $key, string $display): GarmentLabel
    {
        $defaults = (array) config("creative.garment_detection.label_defaults.{$key}", []);

        return GarmentLabel::firstOrCreate(
            ['key' => $key],
            [
                'display' => $display ?: $key,
                'source' => GarmentLabel::SOURCE_AUTO_CREATED,
                'preservation_category' => $defaults['category'] ?? GarmentLabel::CATEGORY_APPEARANCE,
                'default_priority' => $defaults['priority'] ?? GarmentLabel::PRIORITY_MEDIUM,
            ],
        );
    }

    private function resolveDriverName(): string
    {
        return match (true) {
            $this->detector instanceof PythonGarmentPartDetector => 'python',
            $this->detector instanceof PythonZeroShotGarmentPartDetector => 'zeroshot',
            default => 'null',
        };
    }
}
