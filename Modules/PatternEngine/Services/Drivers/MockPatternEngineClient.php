<?php

namespace Modules\PatternEngine\Services\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\DesignParamsDTO;
use Modules\PatternEngine\DTOs\DigitizeSuggestionDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradedPatternResultDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\NamedPointDTO;
use Modules\PatternEngine\DTOs\PatternProfileDTO;
use Modules\PatternEngine\DTOs\PatternProfileSummaryDTO;
use Modules\PatternEngine\DTOs\PatternResultDTO;
use Modules\PatternEngine\DTOs\PieceDTO;
use Modules\PatternEngine\DTOs\ProvenanceDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\DTOs\SegmentDTO;
use Modules\PatternEngine\DTOs\ValidationFindingDTO;
use Modules\PatternEngine\Exceptions\ProfileProtectedException;

/**
 * Servissiz dev/test için sahte kalıp üretici.
 *
 * Geçerli (minimal) bir DXF + sabit bir "front_bodice" parçası döndürür;
 * gerçek ölçü çözümlemesi/geometri üretmez (bkz. Atelier'in `MockPdfDxfConverter`'ı,
 * aynı desen).
 */
class MockPatternEngineClient implements PatternEngineClient
{
    public function generate(GeneratePatternRequestDTO $request): PatternResultDTO
    {
        $disk = (string) config('patternengine.engine.disk');
        $dir = (string) config('patternengine.engine.dxf_dir');
        $fileName = sprintf('%s-%s-%s.mock.dxf', $request->garment, $request->size, (string) Str::uuid());
        $path = trim($dir, '/').'/'.$fileName;
        Storage::disk($disk)->put($path, $this->minimalDxf());

        $piece = new PieceDTO(
            name: 'front_bodice',
            mirrored: true,
            seamAllowance: 10.0,
            grainline: ['neck_center', 'hem_center'],
            points: [
                'neck_center' => new NamedPointDTO('neck_center', 0.0, 82.5),
                'hem_center'  => new NamedPointDTO('hem_center', 0.0, 480.0),
            ],
            segments: [
                new SegmentDTO('neck_center', 'hem_center', kind: 'line', role: 'fold'),
            ],
        );

        $findings = [
            new ValidationFindingDTO(
                ruleId: 'closed_contour',
                passed: true,
                measured: 0.0,
                expected: 0.0,
                delta: 0.0,
                tolerance: 0.01,
                message: 'Mock sürücü — gerçek doğrulama yapılmadı.',
                suggestion: null,
            ),
        ];

        return new PatternResultDTO(
            garment: $request->garment,
            size: $request->size,
            profile: $request->profile,
            pieces: [$piece],
            dxfPath: $path,
            findings: $findings,
            allPassed: true,
            warnings: ['Mock sürücü kullanılıyor — servis kapalı/tanımsız (PATTERN_ENGINE_DRIVER=mock).'],
        );
    }

    public function gradeGenerate(GradePatternRequestDTO $request): GradedPatternResultDTO
    {
        $disk = (string) config('patternengine.engine.disk');
        $dir = (string) config('patternengine.engine.dxf_dir');
        $sizes = array_values(array_unique([$request->baseSize, ...$request->targetSizes]));
        $fileName = sprintf('%s-graded-%s.mock.dxf', $request->garment, (string) Str::uuid());
        $path = trim($dir, '/').'/'.$fileName;
        Storage::disk($disk)->put($path, $this->minimalDxf());

        $piece = new PieceDTO(
            name: 'front_bodice',
            mirrored: true,
            seamAllowance: 10.0,
            grainline: ['neck_center', 'hem_center'],
            points: [
                'neck_center' => new NamedPointDTO('neck_center', 0.0, 82.5),
                'hem_center'  => new NamedPointDTO('hem_center', 0.0, 480.0),
            ],
            segments: [
                new SegmentDTO('neck_center', 'hem_center', kind: 'line', role: 'fold'),
            ],
        );

        $finding = new ValidationFindingDTO(
            ruleId: 'closed_contour',
            passed: true,
            measured: 0.0,
            expected: 0.0,
            delta: 0.0,
            tolerance: 0.01,
            message: 'Mock sürücü — gerçek doğrulama yapılmadı.',
            suggestion: null,
        );

        $piecesBySize = [];
        $findingsBySize = [];
        $allPassedBySize = [];
        foreach ($sizes as $size) {
            $piecesBySize[$size] = [$piece];
            $findingsBySize[$size] = [$finding];
            $allPassedBySize[$size] = true;
        }

        return new GradedPatternResultDTO(
            garment: $request->garment,
            profile: $request->profile,
            sizes: $sizes,
            piecesBySize: $piecesBySize,
            dxfPath: $path,
            findingsBySize: $findingsBySize,
            allPassedBySize: $allPassedBySize,
            warnings: ['Mock sürücü kullanılıyor — servis kapalı/tanımsız (PATTERN_ENGINE_DRIVER=mock).'],
        );
    }

    public function probe(): bool
    {
        // Mock her zaman "ayakta" görünür — servissiz dev/test.
        return true;
    }

    public function listProfiles(): Collection
    {
        return collect([
            new PatternProfileSummaryDTO(
                name: 'ts_default',
                garment: 'tshirt',
                sizes: ['4Y', '8Y', '12Y'],
                source: 'TS_standard_placeholder',
                verifiedByModelist: false,
            ),
        ]);
    }

    public function getProfile(string $name): ?PatternProfileDTO
    {
        if ($name !== 'ts_default') {
            return null;
        }

        return new PatternProfileDTO(
            name: 'ts_default',
            garment: 'tshirt',
            sizes: ['8Y' => ['chest_circ' => 670.0, 'height' => 1280.0]],
            provenance: new ProvenanceDTO(source: 'TS_standard_placeholder', verifiedByModelist: false),
        );
    }

    public function saveProfile(SaveProfileRequestDTO $request): PatternProfileDTO
    {
        if ($request->name === 'ts_default') {
            throw new ProfileProtectedException("'ts_default' referans profildir, API ile değiştirilemez.");
        }

        return new PatternProfileDTO(
            name: $request->name,
            garment: 'tshirt',
            sizes: $request->sizes,
            provenance: $request->provenance,
        );
    }

    public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): DigitizeSuggestionDTO
    {
        return new DigitizeSuggestionDTO(
            designParams: new DesignParamsDTO(
                neckDepthFront: 78.0,
                bodyLength: 480.0,
                hemFlare: 10.0,
                source: ['neck_depth_front' => 'ai', 'body_length' => 'ai', 'hem_flare' => 'ai'],
                confidence: ['neck_depth_front' => 0.6, 'body_length' => 0.6, 'hem_flare' => 0.6],
            ),
            silhouette: ['width_px' => 280.0, 'height_px' => 390.0],
            warnings: ['Mock sürücü kullanılıyor — servis kapalı/tanımsız (PATTERN_ENGINE_DRIVER=mock).'],
        );
    }

    /** Geçerli, boş bir DXF iskeleti (yalnız mock için). */
    private function minimalDxf(): string
    {
        return "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n";
    }
}
