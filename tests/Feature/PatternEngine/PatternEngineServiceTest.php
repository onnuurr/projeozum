<?php

namespace Tests\Feature\PatternEngine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\DesignParamsDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradedPatternResultDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\PatternResultDTO;
use Modules\PatternEngine\Jobs\GeneratePatternJob;
use Modules\PatternEngine\Models\PatternGenerationJob;
use Modules\PatternEngine\Services\Drivers\MockPatternEngineClient;
use Modules\PatternEngine\Services\PatternEngineService;
use RuntimeException;
use Tests\TestCase;

class PatternEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['patternengine.engine.disk' => 'public']);
        $this->app->bind(PatternEngineClient::class, MockPatternEngineClient::class);
    }

    private function service(): PatternEngineService
    {
        return app(PatternEngineService::class);
    }

    public function test_queue_processes_synchronously_and_marks_done(): void
    {
        // QUEUE_CONNECTION=sync (phpunit.xml) → dispatch() job'ı hemen işler.
        $job = $this->service()->queue('tshirt', '8Y', 'ts_default', new DesignParamsDTO());

        $job->refresh();

        $this->assertSame(PatternGenerationJob::STATUS_DONE, $job->status);
        $this->assertNotNull($job->result_dxf_path);
        Storage::disk('public')->assertExists($job->result_dxf_path);
        $this->assertTrue($job->validation_report['all_passed']);
        $this->assertNull($job->error);
    }

    public function test_stale_generation_token_is_ignored(): void
    {
        $job = PatternGenerationJob::create([
            'garment'          => 'tshirt',
            'size'             => '8Y',
            'profile'          => 'ts_default',
            'design_params'    => [],
            'status'           => PatternGenerationJob::STATUS_QUEUED,
            'generation_token' => 'old-token',
        ]);

        // Aradaki bir queue() çağrısı token'ı değiştirmiş gibi simüle et.
        $job->update(['generation_token' => 'new-token']);

        // Eski token'ı taşıyan iş (sync queue → hemen çalışır) satırı işlememeli.
        GeneratePatternJob::dispatch($job->id, 'old-token');

        $job->refresh();
        $this->assertSame(PatternGenerationJob::STATUS_QUEUED, $job->status);
        $this->assertNull($job->result_dxf_path);
    }

    public function test_job_failed_callback_marks_row_failed(): void
    {
        $job = PatternGenerationJob::create([
            'garment'          => 'tshirt',
            'size'             => '8Y',
            'profile'          => 'ts_default',
            'design_params'    => [],
            'status'           => PatternGenerationJob::STATUS_PROCESSING,
            'generation_token' => 'tok-1',
        ]);

        $queueJob = new GeneratePatternJob($job->id, 'tok-1');
        $queueJob->failed(new RuntimeException('kalıp motoru servisi çöktü'));

        $job->refresh();
        $this->assertSame(PatternGenerationJob::STATUS_FAILED, $job->status);
        $this->assertSame('kalıp motoru servisi çöktü', $job->error);
    }

    public function test_generate_throws_when_driver_fails(): void
    {
        $this->app->bind(PatternEngineClient::class, fn () => new class implements PatternEngineClient
        {
            public function generate(GeneratePatternRequestDTO $request): PatternResultDTO
            {
                throw new RuntimeException('servis çöktü');
            }

            public function gradeGenerate(GradePatternRequestDTO $request): GradedPatternResultDTO
            {
                throw new RuntimeException('servis çöktü');
            }

            public function probe(): bool
            {
                return false;
            }

            public function listProfiles(): \Illuminate\Support\Collection
            {
                return collect();
            }

            public function getProfile(string $name): ?\Modules\PatternEngine\DTOs\PatternProfileDTO
            {
                return null;
            }

            public function saveProfile(\Modules\PatternEngine\DTOs\SaveProfileRequestDTO $request): \Modules\PatternEngine\DTOs\PatternProfileDTO
            {
                throw new RuntimeException('servis çöktü');
            }

            public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): \Modules\PatternEngine\DTOs\DigitizeSuggestionDTO
            {
                throw new RuntimeException('servis çöktü');
            }
        });

        $job = PatternGenerationJob::create([
            'garment'          => 'tshirt',
            'size'             => '8Y',
            'profile'          => 'ts_default',
            'design_params'    => [],
            'status'           => PatternGenerationJob::STATUS_PROCESSING,
            'generation_token' => 'tok-2',
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->generate($job);
    }

    public function test_queue_graded_processes_synchronously_and_stores_per_size_report(): void
    {
        $job = $this->service()->queueGraded('tshirt', '8Y', ['4Y', '12Y'], 'ts_default', new DesignParamsDTO());

        $job->refresh();

        $this->assertSame(PatternGenerationJob::STATUS_DONE, $job->status);
        $this->assertTrue($job->isGraded());
        $this->assertSame(['4Y', '12Y'], $job->graded_sizes);
        $this->assertNotNull($job->result_dxf_path);
        Storage::disk('public')->assertExists($job->result_dxf_path);

        $report = $job->validation_report;
        $this->assertArrayHasKey('all_passed_by_size', $report);
        $this->assertArrayHasKey('findings_by_size', $report);
    }

    public function test_queue_without_grade_sizes_is_not_graded(): void
    {
        $job = $this->service()->queue('tshirt', '8Y', 'ts_default', new DesignParamsDTO());

        $this->assertFalse($job->isGraded());
        $this->assertNull($job->graded_sizes);
    }
}
