<?php

namespace Modules\Creative\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Ai\Contracts\TryOnPromptBuilderContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Throwable;

/**
 * Try-on prompt A/B ölçüm yüzeyi: sabit fixture setini (bkz.
 * Modules/Creative/tests/Fixtures/tryon_prompt_cases.php) iki prompt builder
 * SÜRÜMÜNE karşı çalıştırır ve çıktıları karşılaştırır.
 *
 * Bu komut BİLEREK "prompt'a dokunmadan önce" var — yani bugün A ve B
 * varsayılan olarak AYNI (mevcut GeminiTryOnPromptBuilder) sınıfı gösterir;
 * bu durumda her vaka IDENTICAL çıkmalıdır (harness'ın kendisini doğrulayan
 * bir smoke test). Prompt üzerinde bir değişiklik denenmek istendiğinde,
 * değişiklik AYRI bir sınıfa (ör. GeminiTryOnPromptBuilderV2, aynı
 * TryOnPromptBuilderContract'ı implemente eden) yazılır ve --b=... ile
 * gösterilir — mevcut üretim kodu (GeminiTryOn, hâlâ tek somut sınıfı enjekte
 * eder) hiç değişmeden kalır.
 *
 * --mode=text (varsayılan, ÜCRETSİZ): sadece prompt METNİ üretilir/diff'lenir,
 * Gemini'ye hiç istek atılmaz.
 * --mode=image (ÜCRETLİ, gerçek Gemini try-on çağrısı — --confirm-cost
 * ZORUNLU): her vaka için A ve B prompt'larıyla GERÇEK görsel üretilir, yan
 * yana kaydedilir. TryonGenerationRendered event'i BİLEREK fırlatılmaz — bu
 * harness çalıştırmaları Modules/TrainingIngest'in kalıcı eğitim verisine
 * KARIŞMAMALI (bkz. proje hafızası: training ingest, gerçek üretim
 * olaylarını toplar; A/B test çalıştırmaları gerçek üretim değildir).
 */
class TryonPromptCompareCommand extends Command
{
    protected $signature = <<<'SIG'
        creative:tryon-prompt-compare
            {--mode=text : Karşılaştırma modu — text (ücretsiz, sadece prompt metni) | image (GERÇEK, ücretli Gemini try-on çağrısı)}
            {--a=Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder : A sürümü — TryOnPromptBuilderContract implemente eden FQCN}
            {--b=Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder : B sürümü — TryOnPromptBuilderContract implemente eden FQCN}
            {--case=* : Sadece bu case key'lerini çalıştır (verilmezse fixture'daki tüm vakalar)}
            {--out= : Çıktı dizini (verilmezse storage/app/creative/prompt-compare/<zaman damgası>)}
            {--confirm-cost : --mode=image için ZORUNLU; gerçek/ücretli bir Gemini çağrısı yapılacağını bilerek onaylar}
        SIG;

    protected $description = 'Sabit try-on prompt test setini (Fixtures/tryon_prompt_cases.php) iki prompt builder sürümüne karşı çalıştırıp karşılaştırır (metin veya gerçek görsel modu)';

    private const FIXTURE_PATH = __DIR__ . '/../../tests/Fixtures/tryon_prompt_cases.php';

    public function handle(): int
    {
        $mode = $this->option('mode');
        if (! in_array($mode, ['text', 'image'], true)) {
            $this->components->error("Geçersiz --mode: {$mode} (text|image olmalı).");

            return self::FAILURE;
        }

        if ($mode === 'image' && ! $this->option('confirm-cost')) {
            $this->components->error(
                '--mode=image GERÇEK, ÜCRETLİ bir Gemini try-on çağrısı yapar (her vaka × 2 sürüm = '
                . '2 üretim). Bunu bilerek onaylamak için --confirm-cost ekleyin. Hiçbir istek atılmadı.',
            );

            return self::FAILURE;
        }

        if (! is_file(self::FIXTURE_PATH)) {
            $this->components->error('Fixture bulunamadı: ' . self::FIXTURE_PATH);

            return self::FAILURE;
        }

        /** @var array<string,array<string,mixed>> $allCases */
        $allCases = require self::FIXTURE_PATH;

        $requested = $this->option('case');
        if ($requested !== []) {
            $unknown = array_diff($requested, array_keys($allCases));
            if ($unknown !== []) {
                $this->components->error(
                    'Bilinmeyen case key(ler): ' . implode(', ', $unknown)
                    . '. Mevcut: ' . implode(', ', array_keys($allCases)),
                );

                return self::FAILURE;
            }
            $cases = array_intersect_key($allCases, array_flip($requested));
        } else {
            $cases = $allCases;
        }

        $builderA = $this->resolveBuilder($this->option('a'));
        $builderB = $this->resolveBuilder($this->option('b'));
        if ($builderA === null || $builderB === null) {
            return self::FAILURE;
        }

        $outDir = $this->option('out') ?: storage_path('app/creative/prompt-compare/' . now()->format('Ymd_His'));
        File::ensureDirectoryExists($outDir);

        $this->components->info(sprintf(
            'mode=%s  A=%s  B=%s  vaka sayısı=%d  çıktı=%s',
            $mode,
            get_class($builderA),
            get_class($builderB),
            count($cases),
            $outDir,
        ));

        $rows     = [];
        $client   = $mode === 'image' ? app(GeminiClient::class) : null;

        foreach ($cases as $key => $case) {
            $caseDir = $outDir . '/' . $key;
            File::ensureDirectoryExists($caseDir);

            $promptA = $builderA->build($case['extras'], $case['extra_instruction'], $case['protect_list_sentence']);
            $promptB = $builderB->build($case['extras'], $case['extra_instruction'], $case['protect_list_sentence']);

            File::put($caseDir . '/a.sentences.txt', $this->toSentenceLines($promptA));
            File::put($caseDir . '/b.sentences.txt', $this->toSentenceLines($promptB));
            File::put($caseDir . '/a.raw.txt', $promptA);
            File::put($caseDir . '/b.raw.txt', $promptB);

            $identical = hash('sha256', $promptA) === hash('sha256', $promptB);
            $diff      = null;
            if (! $identical) {
                $diff = $this->unifiedDiff($caseDir . '/a.sentences.txt', $caseDir . '/b.sentences.txt');
                File::put($caseDir . '/diff.txt', $diff);
            }

            $row = [
                'case'              => $key,
                'extras'            => count($case['extras']),
                'protect'           => $case['protect_list_sentence'] !== null ? 'evet' : 'hayır',
                'extra_instruction' => $case['extra_instruction'] !== null ? 'evet' : 'hayır',
                'ret_kodları'       => implode(',', $case['rejection_reason_codes']),
                'a_chars'           => mb_strlen($promptA),
                'b_chars'           => mb_strlen($promptB),
                'identical'         => $identical ? 'EVET' : 'HAYIR',
            ];

            if ($mode === 'image') {
                $row = [...$row, ...$this->runImageMode($client, $key, $case, $promptA, $promptB, $caseDir)];
            }

            $rows[] = $row;

            if (! $identical) {
                $this->components->warn("[{$key}] prompt metni FARKLI — diff.txt'e bakın.");
            }
        }

        $manifest = [
            'generated_at'   => now()->toIso8601String(),
            'mode'           => $mode,
            'a_class'        => get_class($builderA),
            'b_class'        => get_class($builderB),
            'fixture'        => self::FIXTURE_PATH,
            'cases'          => $rows,
        ];
        File::put($outDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->table(array_keys($rows[array_key_first($rows)] ?? []), $rows);
        $this->components->info("Manifest: {$outDir}/manifest.json");

        return self::SUCCESS;
    }

    private function resolveBuilder(string $class): ?TryOnPromptBuilderContract
    {
        if (! class_exists($class)) {
            $this->components->error("Sınıf bulunamadı: {$class}");

            return null;
        }

        $instance = app($class);
        if (! $instance instanceof TryOnPromptBuilderContract) {
            $this->components->error("{$class}, TryOnPromptBuilderContract implemente etmiyor.");

            return null;
        }

        return $instance;
    }

    /**
     * Diff'in okunabilir olması için tek satırlık prompt'u ". " sınırında
     * cümlelere böler — GeminiTryOnPromptBuilder çıktı olarak tek bir satır
     * döner (implode(' ', $lines)), bu sadece diff/okuma amaçlı bir
     * yeniden-bölmedir, gerçek prompt'un kendisi DEĞİŞTİRİLMEZ (a.raw.txt /
     * b.raw.txt ham hâli tutar, ölçümler ham metin üstünden hesaplanır).
     */
    private function toSentenceLines(string $prompt): string
    {
        $sentences = preg_split('/(?<=\.)\s+(?=[A-ZİÜÇÖŞĞ])/u', $prompt) ?: [$prompt];

        return implode("\n", array_map('trim', $sentences)) . "\n";
    }

    private function unifiedDiff(string $fileA, string $fileB): string
    {
        $result = Process::run(['diff', '-u', $fileA, $fileB]);

        // diff çıkış kodu 1 = farklar var (normal, hata değil), 2+ = gerçek hata.
        if ($result->exitCode() > 1) {
            return "diff çalıştırılamadı: " . $result->errorOutput();
        }

        return $result->output();
    }

    /**
     * @return array<string,string>
     */
    private function runImageMode(GeminiClient $client, string $key, array $case, string $promptA, string $promptB, string $caseDir): array
    {
        $modelPath   = $this->resolveLocal($case['model_image_path']);
        $garmentPath = $this->resolveLocal($case['garment_image_path']);
        $extraPaths  = array_values(array_filter(array_map(
            fn (array $e) => $this->resolveLocal($e['path'] ?? null),
            $case['extras'],
        )));

        if ($modelPath === null || $garmentPath === null) {
            return ['image' => 'ATLANDI (görsel dosyası bulunamadı)'];
        }

        $images       = [$modelPath, $garmentPath, ...$extraPaths];
        $modelName    = (string) config('creative.ai.gemini.tryon_model') ?: null;
        $imageConfig  = GeminiClient::buildImageConfig(
            (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            (string) config('creative.ai.gemini.image.tryon_size', ''),
        );

        $status = [];
        foreach (['a' => $promptA, 'b' => $promptB] as $tag => $prompt) {
            try {
                $bytes = $client->generateImage($prompt, $images, $modelName, $imageConfig);
                File::put("{$caseDir}/{$tag}.png", $bytes);
                $status[$tag] = 'OK';
            } catch (Throwable $e) {
                $status[$tag] = 'HATA: ' . $e->getMessage();
                $this->components->error("[{$key}/{$tag}] Gemini çağrısı başarısız: " . $e->getMessage());
            }
        }

        return [
            'image_a' => $status['a'],
            'image_b' => $status['b'],
        ];
    }

    private function resolveLocal(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // cropsForTryOn() zaten çözülmüş MUTLAK yollar döner (bkz. GarmentScanService);
        // fixture'daki bazı extras path'leri bu şekilde üretildi. Diğerleri (manuel
        // yüklenen creative/tryon_garments/... ve mannequins/... gibi) medya diskine
        // göre GÖRECELİ — Media::localPath() ile aynı çözümleme production'da da kullanılır.
        if (str_starts_with($path, '/')) {
            return is_file($path) ? $path : null;
        }

        return Media::localPath($path);
    }
}
