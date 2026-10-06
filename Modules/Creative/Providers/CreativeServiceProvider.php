<?php

namespace Modules\Creative\Providers;

use App\Support\ModuleSettingsRegistry;
use Illuminate\Support\Facades\Event;
use Modules\Creative\Jobs\AutoStartMockupJob;
use Modules\Creative\Services\Mockup\MockupSettings;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Events\ProductVariantsSynced;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Creative\Console\Commands\CreativeReviewReportCommand;
use Modules\Creative\Console\Commands\TrainGarmentDetectorCommand;
use Modules\Creative\Console\Commands\TryonPromptCompareCommand;
use Modules\Creative\Console\Commands\MockupGenerateCommand;
use Modules\Creative\Console\Commands\AiUsageReportCommand;
use Modules\Creative\Console\Commands\AiBatchCommand;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;
use Modules\Creative\Services\AiBatchService;
use Modules\Creative\Services\Ai\Contracts\AiUsageStoreContract;
use Modules\Creative\Services\DatabaseAiUsageStore;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMockupGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMockupGenerator;
use Modules\Creative\Services\Ai\Drivers\Python\PythonMockupVision;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\CompositionComposerContract;
use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\GarmentIdentitySummarizerContract;
use Modules\Creative\Services\Ai\Contracts\GarmentPartAnalyzerContract;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\CloseupComposerContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiCloseupComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCloseupComposer;
use Modules\Creative\Services\Ai\TryOnDriverResolver;
use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\Contracts\PosePreviewComposerContract;
use Modules\Creative\Services\Ai\Contracts\RejectionInsightContract;
use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\Drivers\Fal\FalFluxComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiCopyGenerator;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiIdentitySummarizer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMannequinComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMannequinPoseComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiPartAnalyzer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiPosePreviewComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiRejectionInsightGenerator;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiSceneComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCompositionComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCopyGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockIdentitySummarizer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMannequinComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMannequinPoseComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockPartAnalyzer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockPosePreviewComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockRejectionInsightGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockSceneComposer;
use Modules\Creative\Services\Enhancement\ColorLockContract;
use Modules\Creative\Services\Enhancement\GarmentDetailClassifierContract;
use Modules\Creative\Services\Enhancement\GarmentPartDetectorContract;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Creative\Services\Enhancement\NullColorLock;
use Modules\Creative\Services\Enhancement\NullGarmentDetailClassifier;
use Modules\Creative\Services\Enhancement\NullGarmentPartDetector;
use Modules\Creative\Services\Enhancement\NullGarmentPreparer;
use Modules\Creative\Services\Enhancement\NullImageEnhancer;
use Modules\Creative\Services\Enhancement\PythonColorLock;
use Modules\Creative\Services\Enhancement\PythonGarmentDetailClassifier;
use Modules\Creative\Services\Enhancement\PythonGarmentPartDetector;
use Modules\Creative\Services\Enhancement\PythonGarmentPreparer;
use Modules\Creative\Services\Enhancement\PythonImageEnhancer;
use Modules\Creative\Services\Enhancement\PythonZeroShotGarmentPartDetector;
use Modules\Creative\Services\Rendering\PythonRenderer;
use Modules\Creative\Services\Rendering\RendererContract;
use Modules\Creative\Services\Vision\ColorAuditorContract;
use Modules\Creative\Services\Vision\NullColorAuditor;
use Modules\Creative\Services\Vision\NullTextRecognizer;
use Modules\Creative\Services\Vision\PythonColorAuditor;
use Modules\Creative\Services\Vision\PythonTesseractTextRecognizer;
use Modules\Creative\Services\Vision\TextRecognizerContract;

class CreativeServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Creative';

    protected string $nameLower = 'creative';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        CreativeReviewReportCommand::class,
        TrainGarmentDetectorCommand::class,
        TryonPromptCompareCommand::class,
        MockupGenerateCommand::class,
        AiUsageReportCommand::class,
        AiBatchCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Mokap otomatik tetik (Faz 4): ürüne fotoğraf eklenince — koşullar AutoStartMockupJob'da
        // (ayar kapalıysa hiçbir şey yapmaz). Ürün oluşturma görselleri sessiz yazıp ProductCreated fırlatır.
        // Bedenler değişince (ProductVariantsSynced) eksik beden grupları da tamamlanır.
        Event::listen([ProductCreated::class, ProductImagesChanged::class, ProductVariantsSynced::class],
            function (ProductCreated|ProductImagesChanged|ProductVariantsSynced $event) {
                if (app(MockupSettings::class)->autoTrigger()) {
                    AutoStartMockupJob::dispatch($event instanceof ProductImagesChanged ? $event->productId : $event->product->id)
                        ->delay(now()->addMinutes(2));
                }
            });

        // Tek Creative ayar sayfası (mokap: kategori eşleştirme, stilist seçenekleri, poz etiketleri,
        // otomatik tetik, günlük limit): Creative menüsünün sekme satırında çark.
        $this->app->make(ModuleSettingsRegistry::class)->register(
            key: 'creative',
            url: '/creative/settings',
            permission: 'creative-settings.view',
            menuRoots: ['/creative/gallery', '/creative/tryon'],
            tabPermissions: ['mockup-settings.manage'],
        );
    }

    public function register(): void
    {
        parent::register();

        // Render motoru config'e göre seçilir. Şimdilik tek implementasyon
        // (Python) var; soyutlama ileride alternatif motorlara izin verir.
        $this->app->bind(RendererContract::class, function () {
            return match (config('creative.render.engine', 'python')) {
                default => $this->app->make(PythonRenderer::class),
            };
        });

        // Görsel iyileştirme sürücüsü: enabled + driver=python ise Python (opencv),
        // aksi halde passthrough (Null). Üretim sonrası upscale/keskinleştirme katmanı.
        $this->app->bind(ImageEnhancerContract::class, function ($app) {
            $usePython = config('creative.enhance.enabled')
                && config('creative.enhance.driver') === 'python';

            return $app->make($usePython ? PythonImageEnhancer::class : NullImageEnhancer::class);
        });

        // Giydirme öncesi giysi görseli hazırlığı: enabled + driver=python ise Python
        // (Pillow: EXIF + kırpma + boyut), aksi halde passthrough (Null).
        $this->app->bind(GarmentPrepContract::class, function ($app) {
            $usePython = config('creative.garment_prep.enabled')
                && config('creative.garment_prep.driver') === 'python';

            return $app->make($usePython ? PythonGarmentPreparer::class : NullGarmentPreparer::class);
        });

        // Renk sadakati denetimi (Faz Q): enabled + driver=python ise Python (numpy/Pillow
        // Delta E ölçümü), aksi halde passthrough (Null, hiç ölçmez). SADECE ölçer.
        $this->app->bind(ColorAuditorContract::class, function ($app) {
            $usePython = config('creative.color_fidelity.audit.enabled')
                && config('creative.color_fidelity.audit.driver') === 'python';

            return $app->make($usePython ? PythonColorAuditor::class : NullColorAuditor::class);
        });

        // Renk sadakati kilidi (Faz Q): enabled + driver=python ise Python (numpy/Pillow
        // LAB a/b-kanal transferi), aksi halde passthrough (Null, görsele dokunmaz).
        $this->app->bind(ColorLockContract::class, function ($app) {
            $usePython = config('creative.color_fidelity.lock.enabled')
                && config('creative.color_fidelity.lock.driver') === 'python';

            return $app->make($usePython ? PythonColorLock::class : NullColorLock::class);
        });

        // Giysi detay görseli otomatik etiketleme: enabled + driver=python ise Python
        // (yerel zero-shot CLIP), aksi halde passthrough (Null, hiç öneri üretmez).
        $this->app->bind(GarmentDetailClassifierContract::class, function ($app) {
            $usePython = config('creative.detail_classification.enabled')
                && config('creative.detail_classification.driver') === 'python';

            return $app->make($usePython ? PythonGarmentDetailClassifier::class : NullGarmentDetailClassifier::class);
        });

        // Giysi parça tespiti (yaka/cep/etek vb. bbox), öncelik sırası:
        //   1) enabled + driver=python VE fine-tune edilmiş ağırlık dosyası mevcutsa
        //      Python (torchvision) — is_file() kontrolü sayesinde ilk fine-tune
        //      koşulmadan önce hiçbir Python çağrısı yapılmaz. G.4 koşunca (ağırlık
        //      dosyası oluşunca) KOD DEĞİŞİKLİĞİ GEREKMEDEN otomatik buraya geçer.
        //   2) aksi halde, zero_shot.enabled ise OWLv2 bootstrap dedektörü (Faz
        //      G.3b) — eğitim verisi olmadan öneri kutuları üretir; her tespit
        //      source='zeroshot' damgalanır (bkz. GarmentScanService), bu yüzden
        //      bir insan onaylamadan try-on prompt'una/eğitim verisine karışmaz.
        //   3) ikisi de yoksa passthrough (Null).
        $this->app->bind(GarmentPartDetectorContract::class, function ($app) {
            $fineTuned = config('creative.garment_detection.enabled')
                && config('creative.garment_detection.driver') === 'python'
                && is_file((string) config('creative.garment_detection.weights_path'));

            if ($fineTuned) {
                return $app->make(PythonGarmentPartDetector::class);
            }

            $zeroShot = config('creative.garment_detection.enabled')
                && config('creative.garment_detection.zero_shot.enabled');

            return $app->make($zeroShot ? PythonZeroShotGarmentPartDetector::class : NullGarmentPartDetector::class);
        });

        // Giysi parça analizi (renk/desen/doku/donanım, Faz G.5): enabled +
        // driver=gemini VE anahtar varsa Gemini, aksi halde Mock (tüm alanlar
        // confidence=0 — Rule Engine'de otomatik elenir, hiçbir Gemini çağrısı yapılmaz).
        $this->app->bind(GarmentPartAnalyzerContract::class, function ($app) {
            $useGemini = config('creative.garment_detection.analysis.enabled')
                && config('creative.garment_detection.analysis.driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiPartAnalyzer::class : MockPartAnalyzer::class);
        });

        // Bütünsel "ayırt edici özellik" özeti (Faz G.5, madde 10): ayrı bir
        // opsiyonel bayrak (identity_summary_enabled) — parça analizinden bağımsız.
        $this->app->bind(GarmentIdentitySummarizerContract::class, function ($app) {
            $useGemini = config('creative.garment_detection.analysis.identity_summary_enabled')
                && config('creative.garment_detection.analysis.driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiIdentitySummarizer::class : MockIdentitySummarizer::class);
        });

        // AI compose sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(SceneComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiSceneComposer::class : MockSceneComposer::class);
        });

        // AI manken sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(MannequinComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiMannequinComposer::class : MockMannequinComposer::class);
        });

        // AI manken pozu sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(MannequinPoseComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiMannequinPoseComposer::class : MockMannequinPoseComposer::class);
        });

        // AI poz önizleme sürücüsü (bağımsız nötr figür): gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(PosePreviewComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiPosePreviewComposer::class : MockPosePreviewComposer::class);
        });

        // AI try-on sürücüsü: gemini (Nano Banana 2) | fal | mock — seçim mantığı
        // TryOnDriverResolver'da (sayfadaki Gemini/fal.ai seçimi de aynı sınıfı kullanır).
        $this->app->bind(GarmentTryOnContract::class, function ($app) {
            $resolver = $app->make(TryOnDriverResolver::class);

            return $app->make($resolver->classFor($resolver->defaultDriver()));
        });

        // AI maliyet kaydı: iş bağlamı (forSubject) kuyruk işi başına sıfırlansın diye scoped.
        $this->app->scoped(AiUsageRecorder::class);
        // Gemini Batch API bağlam yığını (defer) da iş başına sıfırlansın diye scoped.
        $this->app->scoped(AiBatchService::class);
        $this->app->bind(AiUsageStoreContract::class, DatabaseAiUsageStore::class);

        // Mokap giydirme: AI adımları gemini (anahtar varsa) / mock; görüntü adımları Python
        // (proje .venv'i — MediaPipe + SAM 2.1, Apache 2.0). Bkz. Services/Mockup.
        $this->app->bind(MockupGeneratorContract::class, function ($app) {
            return $app->make(config('creative.ai.gemini.api_key') ? GeminiMockupGenerator::class : MockMockupGenerator::class);
        });
        $this->app->bind(MockupVisionContract::class, PythonMockupVision::class);

        // Yakın plan / detay çekimi sürücüsü: gemini (anahtar varsa), aksi halde mock.
        // Seçilen try-on sürücüsünden bağımsızdır — fal fashn yalnız giydirme yapar,
        // kadraj değiştiremez; yakın plan her zaman Gemini ile üretilir.
        $this->app->bind(CloseupComposerContract::class, function ($app) {
            return $app->make(config('creative.ai.gemini.api_key') ? GeminiCloseupComposer::class : MockCloseupComposer::class);
        });

        // Caption sürücüsü: gemini metin modeli (anahtar varsa), aksi halde mock.
        $this->app->bind(CaptionGeneratorContract::class, function ($app) {
            $useGemini = config('creative.ai.caption_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiCaptionGenerator::class : MockCaptionGenerator::class);
        });

        // Görsel-üstü metin (headline/sub-headline/CTA) sürücüsü: gemini metin
        // modeli (anahtar varsa), aksi halde mock.
        $this->app->bind(CopyGeneratorContract::class, function ($app) {
            $useGemini = config('creative.ai.copy_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiCopyGenerator::class : MockCopyGenerator::class);
        });

        // Ret analiz raporu "ne yapılabilir" önerisi: gemini metin modeli (anahtar
        // varsa), aksi halde şablon tabanlı mock (bkz. CreativeReviewReportCommand).
        $this->app->bind(RejectionInsightContract::class, function ($app) {
            $useGemini = (bool) config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiRejectionInsightGenerator::class : MockRejectionInsightGenerator::class);
        });

        // AI tam-post kompozisyon sürücüsü (Faz J): fal (anahtar varsa), aksi
        // halde mock — bkz. Modules/Creative/config/config.php `composition`.
        $this->app->bind(CompositionComposerContract::class, function ($app) {
            $useFal = config('creative.composition.driver') === 'fal'
                && config('creative.ai.fal.key');

            return $app->make($useFal ? FalFluxComposer::class : MockCompositionComposer::class);
        });

        // OCR metin tanıma (Faz J): enabled + driver=python ise Python
        // (pytesseract), aksi halde passthrough (Null, hiç kelime bulamaz —
        // CreativeRenderService bunu ai_compose'u hiç çalıştırmama sinyali sayar).
        $this->app->bind(TextRecognizerContract::class, function ($app) {
            $usePython = config('creative.composition.ocr.enabled')
                && config('creative.composition.ocr.driver') === 'python';

            return $app->make($usePython ? PythonTesseractTextRecognizer::class : NullTextRecognizer::class);
        });
    }
}
