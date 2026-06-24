<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Concept\ConceptRequest;
use Modules\Atelier\Services\Concept\ConceptStudioService;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\Atelier\Services\Concept\Drivers\MockConceptGenerator;
use Tests\TestCase;

class ConceptStudioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('atelier.concept.disk', 'public');
        $this->app->bind(ConceptImageGeneratorContract::class, MockConceptGenerator::class);
    }

    private function service(): ConceptStudioService
    {
        return app(ConceptStudioService::class);
    }

    private function pattern(): Pattern
    {
        return Pattern::create([
            'name' => 'Çocuk Tulum', 'product_type' => 'tulum',
            'size_range' => '116-122-128-134', 'status' => Pattern::STATUS_APPROVED,
        ]);
    }

    public function test_create_makes_processing_card_without_images(): void
    {
        $card = $this->service()->create(new ConceptRequest(
            productType: 'tulum',
            description: 'lacivert çiçekli kışlık tulum',
            motif: 'küçük çiçekler',
            count: 2,
            source: DesignCard::SOURCE_CONCEPT_FIRST,
        ));

        $this->assertSame(DesignCard::GENERATION_PROCESSING, $card->generation_status);
        $this->assertNull($card->pattern_id);
        $this->assertSame([], $card->generated_images);
        // Tarif, job'ın yeniden kurması için saklandı.
        $this->assertSame('küçük çiçekler', $card->request_params['motif']);
        $this->assertSame(2, $card->request_params['count']);
    }

    public function test_run_generation_fills_images_and_marks_done(): void
    {
        $card = $this->service()->create(new ConceptRequest(
            productType: 'tulum', count: 3, source: DesignCard::SOURCE_CONCEPT_FIRST,
        ));

        $card = $this->service()->runGeneration($card);

        $this->assertSame(DesignCard::GENERATION_DONE, $card->generation_status);
        $this->assertSame(DesignCard::STATUS_GENERATED, $card->status);
        $this->assertCount(3, $card->generated_images);
        foreach ($card->generated_images as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_pattern_first_binds_card_on_create(): void
    {
        $pattern = $this->pattern();
        $card = $this->service()->create(new ConceptRequest(
            productType: 'tulum', count: 1,
            source: DesignCard::SOURCE_PATTERN_FIRST, patternId: $pattern->id,
        ));

        $this->assertSame($pattern->id, $card->pattern_id);
        $this->assertSame(DesignCard::GENERATION_PROCESSING, $card->generation_status);
    }

    public function test_pattern_first_requires_valid_pattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->create(new ConceptRequest(
            productType: 'tulum',
            source: DesignCard::SOURCE_PATTERN_FIRST, patternId: 999999,
        ));
    }

    public function test_run_generation_failure_marks_failed_and_rethrows(): void
    {
        $this->app->bind(ConceptImageGeneratorContract::class, fn () => new class implements ConceptImageGeneratorContract {
            public function generate(ConceptRequest $request): array
            {
                throw new \RuntimeException('gemini 500');
            }
        });

        $card = $this->service()->create(new ConceptRequest(productType: 'tulum', count: 1));

        try {
            $this->service()->runGeneration($card);
            $this->fail('İstisna bekleniyordu.');
        } catch (\RuntimeException $e) {
            $this->assertSame('gemini 500', $e->getMessage());
        }

        $this->assertSame(DesignCard::GENERATION_FAILED, $card->fresh()->generation_status);
        $this->assertStringContainsString('gemini 500', $card->fresh()->generation_error);
    }

    public function test_match_pattern_links_card(): void
    {
        $card = $this->service()->runGeneration(
            $this->service()->create(new ConceptRequest(productType: 'ceket', count: 1))
        );
        $pattern = $this->pattern();

        $matched = $this->service()->matchPattern($card, $pattern);

        $this->assertSame($pattern->id, $matched->pattern_id);
        $this->assertSame(DesignCard::STATUS_MATCHED, $matched->status);
    }
}
