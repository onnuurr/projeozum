<?php

namespace Tests\Feature\PatternEngine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\DTOs\DigitizeSuggestionDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradedPatternResultDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\PatternProfileDTO;
use Modules\PatternEngine\DTOs\PatternResultDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\Exceptions\PatternGenerationFailedException;
use Modules\PatternEngine\Services\Drivers\MockPatternEngineClient;
use Modules\PatternEngine\Services\PatternEngineService;
use Spatie\Permission\Models\Permission;
use RuntimeException;
use Tests\TestCase;

class PatternDigitizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['patternengine.engine.disk' => 'public']);
        $this->app->bind(PatternEngineClient::class, MockPatternEngineClient::class);
    }

    private function authorizedUser(): User
    {
        Permission::firstOrCreate(['name' => 'patternengine.manage', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('patternengine.manage');

        return $user;
    }

    public function test_plain_user_cannot_digitize(): void
    {
        $plain = User::factory()->create();

        $response = $this->actingAs($plain)->post('/pattern-engine/digitize', [
            'garment' => 'tshirt',
            'size'    => '8Y',
            'image'   => UploadedFile::fake()->image('flat.png'),
        ]);

        $response->assertForbidden();
    }

    public function test_permission_holder_gets_design_params_suggestion(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/digitize', [
            'garment' => 'tshirt',
            'size'    => '8Y',
            'profile' => 'ts_default',
            'image'   => UploadedFile::fake()->image('flat.png'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'design_params' => ['neck_depth_front', 'source', 'confidence'],
            'silhouette',
            'warnings',
        ]);
        $this->assertSame('ai', $response->json('design_params.source.neck_depth_front'));

        // Geçici yükleme iş bitince silinmiş olmalı.
        Storage::disk('public')->assertDirectoryEmpty('pattern-engine/digitize-uploads');
    }

    public function test_rejects_non_image_upload(): void
    {
        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/digitize', [
            'garment' => 'tshirt',
            'size'    => '8Y',
            'image'   => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_upload_cleaned_up_even_when_driver_fails(): void
    {
        $this->app->bind(PatternEngineClient::class, fn () => new class implements PatternEngineClient
        {
            public function generate(GeneratePatternRequestDTO $request): PatternResultDTO
            {
                throw new RuntimeException('n/a');
            }

            public function gradeGenerate(GradePatternRequestDTO $request): GradedPatternResultDTO
            {
                throw new RuntimeException('n/a');
            }

            public function probe(): bool
            {
                return false;
            }

            public function listProfiles(): \Illuminate\Support\Collection
            {
                return collect();
            }

            public function getProfile(string $name): ?PatternProfileDTO
            {
                return null;
            }

            public function saveProfile(SaveProfileRequestDTO $request): PatternProfileDTO
            {
                throw new RuntimeException('n/a');
            }

            public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): DigitizeSuggestionDTO
            {
                throw new PatternGenerationFailedException('servis çöktü');
            }
        });

        $response = $this->actingAs($this->authorizedUser())->post('/pattern-engine/digitize', [
            'garment' => 'tshirt',
            'size'    => '8Y',
            'image'   => UploadedFile::fake()->image('flat.png'),
        ]);

        $response->assertStatus(422);
        Storage::disk('public')->assertDirectoryEmpty('pattern-engine/digitize-uploads');
    }
}
