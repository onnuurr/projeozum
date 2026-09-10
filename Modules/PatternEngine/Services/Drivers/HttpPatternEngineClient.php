<?php

namespace Modules\PatternEngine\Services\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\DigitizeSuggestionDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradedPatternResultDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\PatternProfileDTO;
use Modules\PatternEngine\DTOs\PatternProfileSummaryDTO;
use Modules\PatternEngine\DTOs\PatternResultDTO;
use Modules\PatternEngine\DTOs\PieceDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\DTOs\ValidationFindingDTO;
use Modules\PatternEngine\Exceptions\PatternGenerationFailedException;
use Modules\PatternEngine\Exceptions\ProfileProtectedException;

/**
 * Kalıp üretimini FastAPI mikroservisine (pattern-core) gönderip DXF + doğrulama alır.
 */
class HttpPatternEngineClient implements PatternEngineClient
{
    public function generate(GeneratePatternRequestDTO $request): PatternResultDTO
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout((int) config('patternengine.engine.timeout', 120))
            ->asJson()
            ->post("{$base}/generate", $request->toArray());

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Kalıp motoru servisi başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        $json = $response->json() ?? [];

        $dxfBase64 = (string) ($json['dxf_base64'] ?? '');
        $dxf = base64_decode($dxfBase64, true);
        if ($dxf === false || $dxf === '') {
            throw new PatternGenerationFailedException('Kalıp motoru servisi geçerli bir DXF döndürmedi.');
        }

        $disk = (string) config('patternengine.engine.disk');
        $dir = (string) config('patternengine.engine.dxf_dir');
        $fileName = sprintf('%s-%s-%s.dxf', $request->garment, $request->size, (string) Str::uuid());
        $path = trim($dir, '/').'/'.$fileName;

        if (! Storage::disk($disk)->put($path, $dxf)) {
            throw new PatternGenerationFailedException("DXF diske yazılamadı: {$disk}:{$path}");
        }

        $patternJson = $json['pattern'] ?? [];
        $pieces = $this->piecesFromJson($patternJson);

        $findings = array_map(
            static fn (array $finding) => ValidationFindingDTO::fromArray($finding),
            $json['validation'] ?? [],
        );

        return new PatternResultDTO(
            garment: (string) ($patternJson['garment'] ?? $request->garment),
            size: (string) ($patternJson['size'] ?? $request->size),
            profile: (string) ($patternJson['profile'] ?? $request->profile),
            pieces: $pieces,
            dxfPath: $path,
            findings: $findings,
            allPassed: (bool) ($json['all_passed'] ?? false),
            warnings: array_values(array_filter((array) ($json['warnings'] ?? []), 'is_string')),
        );
    }

    public function gradeGenerate(GradePatternRequestDTO $request): GradedPatternResultDTO
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout((int) config('patternengine.engine.timeout', 120))
            ->asJson()
            ->post("{$base}/grade", $request->toArray());

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Kalıp motoru gradasyon servisi başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        $json = $response->json() ?? [];

        $dxfBase64 = (string) ($json['dxf_base64'] ?? '');
        $dxf = base64_decode($dxfBase64, true);
        if ($dxf === false || $dxf === '') {
            throw new PatternGenerationFailedException('Kalıp motoru gradasyon servisi geçerli bir DXF döndürmedi.');
        }

        $disk = (string) config('patternengine.engine.disk');
        $dir = (string) config('patternengine.engine.dxf_dir');
        $sizesLabel = implode('+', (array) ($json['sizes'] ?? [$request->baseSize]));
        $fileName = sprintf('%s-%s-graded-%s.dxf', $request->garment, $sizesLabel, (string) Str::uuid());
        $path = trim($dir, '/').'/'.$fileName;

        if (! Storage::disk($disk)->put($path, $dxf)) {
            throw new PatternGenerationFailedException("Gradasyonlu DXF diske yazılamadı: {$disk}:{$path}");
        }

        $sizes = array_values(array_filter((array) ($json['sizes'] ?? []), 'is_string'));

        $piecesBySize = [];
        foreach ((array) ($json['patterns'] ?? []) as $size => $patternJson) {
            $piecesBySize[(string) $size] = $this->piecesFromJson((array) $patternJson);
        }

        $findingsBySize = [];
        foreach ((array) ($json['validation'] ?? []) as $size => $findings) {
            $findingsBySize[(string) $size] = array_map(
                static fn (array $finding) => ValidationFindingDTO::fromArray($finding),
                (array) $findings,
            );
        }

        $allPassedBySize = [];
        foreach ((array) ($json['all_passed_by_size'] ?? []) as $size => $passed) {
            $allPassedBySize[(string) $size] = (bool) $passed;
        }

        return new GradedPatternResultDTO(
            garment: (string) ($json['garment'] ?? $request->garment),
            profile: (string) ($json['profile'] ?? $request->profile),
            sizes: $sizes,
            piecesBySize: $piecesBySize,
            dxfPath: $path,
            findingsBySize: $findingsBySize,
            allPassedBySize: $allPassedBySize,
            warnings: array_values(array_filter((array) ($json['warnings'] ?? []), 'is_string')),
        );
    }

    /** @return array<int,PieceDTO> */
    private function piecesFromJson(array $patternJson): array
    {
        return array_map(
            static fn (array $piece) => PieceDTO::fromArray($piece),
            $patternJson['pieces'] ?? [],
        );
    }

    public function probe(): bool
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        try {
            $response = Http::timeout(10)->get("{$base}/health");
        } catch (\Throwable $e) {
            return false; // servis kapalı/ulaşılamıyor → akışı engelleme, sadece durumu bildir
        }

        return $response->successful();
    }

    public function listProfiles(): Collection
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout(15)->get("{$base}/profiles");

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Profil listesi alınamadı (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 300),
            ));
        }

        $profiles = (array) ($response->json('profiles') ?? []);

        return collect($profiles)->map(
            static fn (array $profile) => PatternProfileSummaryDTO::fromArray($profile),
        );
    }

    public function getProfile(string $name): ?PatternProfileDTO
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout(15)->get("{$base}/profiles/".rawurlencode($name));

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Profil bilgisi alınamadı (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 300),
            ));
        }

        return PatternProfileDTO::fromArray((array) $response->json());
    }

    public function saveProfile(SaveProfileRequestDTO $request): PatternProfileDTO
    {
        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout(30)
            ->asJson()
            ->put("{$base}/profiles/".rawurlencode($request->name), $request->toArray());

        if ($response->status() === 403) {
            throw new ProfileProtectedException((string) $response->json('detail'));
        }

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Profil kaydedilemedi (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return PatternProfileDTO::fromArray((array) $response->json());
    }

    public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): DigitizeSuggestionDTO
    {
        if (! is_file($absoluteImagePath)) {
            throw new PatternGenerationFailedException("Digitize edilecek görsel bulunamadı: {$absoluteImagePath}");
        }

        $base = rtrim((string) config('patternengine.engine.service_url'), '/');

        $response = Http::timeout((int) config('patternengine.engine.timeout', 120))
            ->attach('image', (string) file_get_contents($absoluteImagePath), basename($absoluteImagePath))
            ->post("{$base}/digitize", [
                'garment' => $garment,
                'size'    => $size,
                'profile' => $profile,
            ]);

        if ($response->failed()) {
            throw new PatternGenerationFailedException(sprintf(
                'Digitizer servisi başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return DigitizeSuggestionDTO::fromArray((array) $response->json());
    }
}
