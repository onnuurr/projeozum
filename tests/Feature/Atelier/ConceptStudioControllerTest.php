<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Atelier\Jobs\GenerateConceptJob;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\Atelier\Services\Concept\Drivers\MockConceptGenerator;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConceptStudioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('atelier.concept.disk', 'public');
        $this->app->bind(ConceptImageGeneratorContract::class, MockConceptGenerator::class);

        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.design.manage', 'guard_name' => 'web']);
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('atelier.view', 'atelier.design.manage');
    }

    public function test_generate_creates_processing_card_and_dispatches(): void
    {
        Queue::fake();

        $this->actingAs($this->manager)
            ->post('/atelier/concepts', [
                'product_type' => 'tulum',
                'source'       => DesignCard::SOURCE_CONCEPT_FIRST,
                'motif'        => 'çiçek',
                'count'        => 2,
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $card = DesignCard::first();
        $this->assertNotNull($card);
        $this->assertSame('tulum', $card->product_type);
        $this->assertSame(DesignCard::GENERATION_PROCESSING, $card->generation_status);
        $this->assertSame([], $card->generated_images);
        $this->assertSame($this->manager->id, $card->created_by);
        Queue::assertPushed(GenerateConceptJob::class, 1);
    }

    public function test_job_run_completes_card_with_images(): void
    {
        // Senkron çalıştır: kart işlenince görsellerle dolup 'generated' olur.
        $this->actingAs($this->manager)
            ->post('/atelier/concepts', [
                'product_type' => 'tulum', 'source' => DesignCard::SOURCE_CONCEPT_FIRST, 'count' => 2,
            ])
            ->assertRedirect();

        $card = DesignCard::first();
        (new GenerateConceptJob($card->id))->handle(app(\Modules\Atelier\Services\Concept\ConceptStudioService::class));

        $card->refresh();
        $this->assertSame(DesignCard::GENERATION_DONE, $card->generation_status);
        $this->assertCount(2, $card->generated_images);
    }

    public function test_regenerate_requeues_failed_card(): void
    {
        Queue::fake();
        $card = DesignCard::create([
            'source' => DesignCard::SOURCE_CONCEPT_FIRST, 'product_type' => 'tulum',
            'generated_images' => [], 'status' => DesignCard::STATUS_DRAFT,
            'generation_status' => DesignCard::GENERATION_FAILED, 'generation_error' => 'gemini 500',
            'request_params' => ['productType' => 'tulum', 'count' => 1],
        ]);

        $this->actingAs($this->manager)
            ->post("/atelier/concepts/{$card->id}/regenerate")
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(DesignCard::GENERATION_PROCESSING, $card->fresh()->generation_status);
        Queue::assertPushed(GenerateConceptJob::class, 1);
    }

    public function test_pattern_first_without_pattern_fails_validation(): void
    {
        $this->actingAs($this->manager)
            ->post('/atelier/concepts', [
                'product_type' => 'tulum',
                'source'       => DesignCard::SOURCE_PATTERN_FIRST,
                'count'        => 1,
            ])
            ->assertSessionHasErrors('pattern_id');

        $this->assertSame(0, DesignCard::count());
    }

    public function test_match_links_concept_to_pattern(): void
    {
        $pattern = Pattern::create(['name' => 'T', 'product_type' => 'tulum', 'status' => Pattern::STATUS_APPROVED]);
        $card = DesignCard::create([
            'source' => DesignCard::SOURCE_CONCEPT_FIRST, 'product_type' => 'tulum',
            'generated_images' => ['atelier/concepts/x.png'], 'status' => DesignCard::STATUS_GENERATED,
        ]);

        $this->actingAs($this->manager)
            ->post("/atelier/concepts/{$card->id}/match", ['pattern_id' => $pattern->id])
            ->assertRedirect();

        $this->assertSame($pattern->id, $card->fresh()->pattern_id);
        $this->assertSame(DesignCard::STATUS_MATCHED, $card->fresh()->status);
    }

    public function test_view_permission_cannot_generate(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.view');

        // Listeyi görebilir
        $this->actingAs($viewer)->get('/atelier/concepts')->assertOk();

        // Ama üretemez
        $this->actingAs($viewer)
            ->post('/atelier/concepts', [
                'product_type' => 'tulum', 'source' => DesignCard::SOURCE_CONCEPT_FIRST, 'count' => 1,
            ])
            ->assertForbidden();
    }
}
