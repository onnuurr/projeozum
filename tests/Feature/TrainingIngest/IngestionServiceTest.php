<?php

namespace Tests\Feature\TrainingIngest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TrainingIngest\Exceptions\IngestionValidationException;
use Modules\TrainingIngest\Models\Asset;
use Modules\TrainingIngest\Models\Generation;
use Modules\TrainingIngest\Models\GenerationInput;
use Modules\TrainingIngest\Models\IngestionLog;
use Modules\TrainingIngest\Models\Review;
use Modules\TrainingIngest\Services\IngestionService;
use Tests\TestCase;

/**
 * training şeması Postgres'e özgüdür (bkz. SkipsNonPostgres) — bu testler sqlite'ta
 * ÇALIŞAMAZ, RefreshDatabase da 'training' bağlantısını kapsamaz. Bu yüzden: (1) gerçek
 * bir 'training' bağlantısı yoksa testler atlanır (henüz training_writer rolü/migration
 * kurulmadıysa bu normaldir — bkz. Modules/TrainingIngest/README.md), (2) varsa her test
 * kendi transaction'ında çalışır ve sonunda rollback edilir (RefreshDatabase'in sqlite
 * tarafı için yaptığının 'training' bağlantısı için elle karşılığı).
 */
class IngestionServiceTest extends TestCase
{
    private IngestionService $ingestion;

    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->trainingIsReachable()) {
            $this->markTestSkipped("Gerçek bir Postgres 'training' bağlantısı yok — bkz. README kurulum adımları.");
        }

        DB::connection('training')->beginTransaction();

        $this->ingestion = $this->app->make(IngestionService::class);
        $this->tmpDir = sys_get_temp_dir() . '/training_ingest_test_' . Str::random(8);
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        if ($this->trainingIsReachable()) {
            DB::connection('training')->rollBack();
        }

        if ($this->tmpDir !== '') {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    private function trainingIsReachable(): bool
    {
        try {
            DB::connection('training')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function fakeImage(string $name, string $content = 'fake-bytes'): string
    {
        $path = "{$this->tmpDir}/{$name}.png";
        file_put_contents($path, $content);

        return $path;
    }

    private function captureGeneration(string $sourceGenerationId, array $overrides = []): Generation
    {
        $args = array_merge([
            'sourceGenerationId'      => $sourceGenerationId,
            'mannequinExternalId'     => 'mannequin-' . Str::random(6),
            'poseExternalId'          => 'pose-' . Str::random(6),
            'productExternalId'       => 'product-' . Str::random(6),
            'productSku'              => 'SKU-1',
            'productName'             => 'Test Ürün',
            'productCategory'         => 'mont',
            'renderedPrompt'          => 'Nihai prompt metni.',
            'canonicalPromptTemplate' => 'Statik şablon metni v1.',
            'modelProvider'           => 'google',
            'modelName'               => 'gemini-3.1-flash-image',
            'modelParams'             => ['aspectRatio' => '3:4'],
            'latencyMs'               => 4200,
            'requestedAt'             => now(),
            'orderedInputs'           => [
                ['local_path' => $this->fakeImage('mannequin'), 'kind' => GenerationInput::KIND_MANNEQUIN],
                ['local_path' => $this->fakeImage('front'), 'kind' => GenerationInput::KIND_FRONT],
            ],
        ], $overrides);

        return $this->ingestion->captureGeneration(...$args);
    }

    public function test_idempotent_generation_capture_writes_only_one_row(): void
    {
        $token = 'gen-' . Str::random(12);

        $this->captureGeneration($token);
        $this->captureGeneration($token);

        $this->assertSame(1, Generation::query()->where('source_generation_id', $token)->count());
        $this->assertSame(
            IngestionLog::STATUS_SKIPPED,
            IngestionLog::query()->where('source_generation_id', $token)->latest('created_at')->value('status'),
        );
    }

    public function test_generation_without_product_input_rolls_back_completely(): void
    {
        $token = 'gen-' . Str::random(12);

        try {
            $this->captureGeneration($token, [
                'orderedInputs' => [
                    ['local_path' => $this->fakeImage('mannequin-only'), 'kind' => GenerationInput::KIND_MANNEQUIN],
                ],
            ]);
            $this->fail('IngestionValidationException bekleniyordu.');
        } catch (IngestionValidationException) {
            // beklenen
        }

        $this->assertSame(0, Generation::query()->where('source_generation_id', $token)->count());
        $this->assertSame(0, GenerationInput::query()->whereHas(
            'generation', fn ($q) => $q->where('source_generation_id', $token)
        )->count());
    }

    public function test_empty_rendered_prompt_is_rejected(): void
    {
        $this->expectException(IngestionValidationException::class);

        $this->captureGeneration('gen-' . Str::random(12), ['renderedPrompt' => '  ']);
    }

    public function test_same_image_content_across_two_generations_dedupes_to_one_asset(): void
    {
        $shared = $this->fakeImage('shared-garment', 'identical-bytes');

        $this->captureGeneration('gen-' . Str::random(12), [
            'orderedInputs' => [
                ['local_path' => $this->fakeImage('m1'), 'kind' => GenerationInput::KIND_MANNEQUIN],
                ['local_path' => $shared, 'kind' => GenerationInput::KIND_FRONT],
            ],
        ]);
        $this->captureGeneration('gen-' . Str::random(12), [
            'orderedInputs' => [
                ['local_path' => $this->fakeImage('m2'), 'kind' => GenerationInput::KIND_MANNEQUIN],
                ['local_path' => $shared, 'kind' => GenerationInput::KIND_FRONT],
            ],
        ]);

        $sha256 = hash('sha256', 'identical-bytes');
        $this->assertSame(1, Asset::query()->where('content_sha256', $sha256)->count());
        $this->assertSame(2, GenerationInput::query()->whereHas(
            'asset', fn ($q) => $q->where('content_sha256', $sha256)
        )->count());
    }

    public function test_rejected_verdict_without_reason_code_is_rejected(): void
    {
        $token = 'gen-' . Str::random(12);
        $this->captureGeneration($token);

        $this->expectException(IngestionValidationException::class);

        $this->ingestion->captureReview(
            sourceGenerationId: $token,
            outputLocalPath: $this->fakeImage('output'),
            verdict: Review::VERDICT_REJECTED,
            reasonCode: null,
            reasonText: null,
            severity: null,
            reviewer: 'Test Kullanıcı',
            reviewedAt: now(),
        );
    }

    public function test_approved_review_is_captured_end_to_end(): void
    {
        $token = 'gen-' . Str::random(12);
        $generation = $this->captureGeneration($token);

        $review = $this->ingestion->captureReview(
            sourceGenerationId: $token,
            outputLocalPath: $this->fakeImage('output'),
            verdict: Review::VERDICT_APPROVED,
            reasonCode: null,
            reasonText: null,
            severity: null,
            reviewer: 'Test Kullanıcı',
            reviewedAt: now(),
        );

        $this->assertSame(Review::VERDICT_APPROVED, $review->verdict);
        $this->assertSame($generation->id, $review->generationOutput->generation_id);
    }

    public function test_review_for_unknown_generation_throws_retryable_not_validation_exception(): void
    {
        try {
            $this->ingestion->captureReview(
                sourceGenerationId: 'gen-does-not-exist-' . Str::random(8),
                outputLocalPath: $this->fakeImage('orphan-output'),
                verdict: Review::VERDICT_APPROVED,
                reasonCode: null,
                reasonText: null,
                severity: null,
                reviewer: 'Test Kullanıcı',
                reviewedAt: now(),
            );
            $this->fail('RuntimeException bekleniyordu.');
        } catch (IngestionValidationException $e) {
            $this->fail('Kalıcı (retry edilmeyen) IngestionValidationException fırlatıldı, oysa bu geçici bir durum: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('henüz training şemasında yok', $e->getMessage());
        }
    }
}
