<?php

namespace Modules\PatternEngine\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\DesignParamsDTO;
use Modules\PatternEngine\DTOs\DigitizeSuggestionDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\PatternProfileDTO;
use Modules\PatternEngine\DTOs\PatternProfileSummaryDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\Jobs\GeneratePatternJob;
use Modules\PatternEngine\Models\PatternGenerationJob;

/**
 * Kalıp üretim orkestratörü — Creative'in `ProductOnModelService`si ile aynı
 * desen: `queue()`/`queueGraded()` bir satır oluşturup asenkron işi kuyruğa
 * alır, `generate()` gerçek üretimi yapar (job içinden çağrılır).
 */
class PatternEngineService
{
    public function __construct(private readonly PatternEngineClient $client) {}

    /**
     * Yeni bir (tekil beden) kalıp üretim talebi kuyruğa alır.
     */
    public function queue(
        string $garment,
        string $size,
        string $profile,
        DesignParamsDTO $designParams,
        ?string $externalReference = null,
    ): PatternGenerationJob {
        return $this->createAndDispatch([
            'external_reference' => $externalReference,
            'garment'            => $garment,
            'size'               => $size,
            'graded_sizes'       => null,
            'profile'            => $profile,
            'design_params'      => $designParams->toArray(),
        ]);
    }

    /**
     * Yeni bir gradasyonlu kalıp üretim talebi kuyruğa alır (§4.5, Sprint 3).
     *
     * @param  array<int,string>  $targetSizes  taban beden dışındaki ek bedenler
     */
    public function queueGraded(
        string $garment,
        string $baseSize,
        array $targetSizes,
        string $profile,
        DesignParamsDTO $designParams,
        ?string $externalReference = null,
    ): PatternGenerationJob {
        return $this->createAndDispatch([
            'external_reference' => $externalReference,
            'garment'            => $garment,
            'size'               => $baseSize,
            'graded_sizes'       => array_values(array_unique($targetSizes)),
            'profile'            => $profile,
            'design_params'      => $designParams->toArray(),
        ]);
    }

    private function createAndDispatch(array $attributes): PatternGenerationJob
    {
        $token = (string) Str::uuid();

        $job = PatternGenerationJob::create([
            ...$attributes,
            'status'           => PatternGenerationJob::STATUS_QUEUED,
            'generation_token' => $token,
        ]);

        GeneratePatternJob::dispatch($job->id, $token);

        return $job;
    }

    /**
     * Kuyruktaki işi işler (yalnızca `GeneratePatternJob::handle()` içinden çağrılır).
     *
     * @throws \Modules\PatternEngine\Exceptions\PatternGenerationFailedException
     */
    public function generate(PatternGenerationJob $job): void
    {
        if ($job->isGraded()) {
            $this->generateGraded($job);

            return;
        }

        $designParams = DesignParamsDTO::fromArray($job->design_params ?? []);

        $request = new GeneratePatternRequestDTO(
            garment: $job->garment,
            size: $job->size,
            profile: $job->profile,
            designParams: $designParams,
        );

        $result = $this->client->generate($request);

        $job->update([
            'status'            => PatternGenerationJob::STATUS_DONE,
            'result_dxf_path'   => $result->dxfPath,
            'validation_report' => [
                'all_passed' => $result->allPassed,
                'findings'   => array_map(static fn ($f) => [
                    'rule_id'    => $f->ruleId,
                    'passed'     => $f->passed,
                    'measured'   => $f->measured,
                    'expected'   => $f->expected,
                    'delta'      => $f->delta,
                    'tolerance'  => $f->tolerance,
                    'message'    => $f->message,
                    'suggestion' => $f->suggestion,
                ], $result->findings),
                'warnings'   => $result->warnings,
            ],
            'error' => null,
        ]);
    }

    private function generateGraded(PatternGenerationJob $job): void
    {
        $designParams = DesignParamsDTO::fromArray($job->design_params ?? []);

        $request = new GradePatternRequestDTO(
            garment: $job->garment,
            baseSize: $job->size,
            targetSizes: $job->graded_sizes ?? [],
            profile: $job->profile,
            designParams: $designParams,
        );

        $result = $this->client->gradeGenerate($request);

        $findingsBySize = [];
        foreach ($result->findingsBySize as $size => $findings) {
            $findingsBySize[$size] = array_map(static fn ($f) => [
                'rule_id'    => $f->ruleId,
                'passed'     => $f->passed,
                'measured'   => $f->measured,
                'expected'   => $f->expected,
                'delta'      => $f->delta,
                'tolerance'  => $f->tolerance,
                'message'    => $f->message,
                'suggestion' => $f->suggestion,
            ], $findings);
        }

        $job->update([
            'status'            => PatternGenerationJob::STATUS_DONE,
            'result_dxf_path'   => $result->dxfPath,
            'validation_report' => [
                'all_passed_by_size' => $result->allPassedBySize,
                'findings_by_size'   => $findingsBySize,
                'warnings'           => $result->warnings,
            ],
            'error' => null,
        ]);
    }

    public function probe(): bool
    {
        return $this->client->probe();
    }

    /** @return Collection<int,PatternProfileSummaryDTO> */
    public function listProfiles(): Collection
    {
        return $this->client->listProfiles();
    }

    public function getProfile(string $name): ?PatternProfileDTO
    {
        return $this->client->getProfile($name);
    }

    public function saveProfile(SaveProfileRequestDTO $request): PatternProfileDTO
    {
        return $this->client->saveProfile($request);
    }

    public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): DigitizeSuggestionDTO
    {
        return $this->client->digitize($garment, $size, $profile, $absoluteImagePath);
    }
}
