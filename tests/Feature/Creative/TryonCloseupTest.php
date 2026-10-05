<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\CloseupComposerContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCloseupComposer;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Creative\Services\ProductCloseupService;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Yakın plan / detay çekimleri: yalnız onaylı tam boy giydirmeden kuyruğa alınır,
 * aynı tabloda shot=<kadraj> satırı olarak tutulur ve aynı onay/yayın akışından geçer.
 */
class TryonCloseupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        config(['inertia.testing.ensure_pages_exist' => false]);
        foreach (['creative.view', 'creative.asset.manage', 'creative.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.asset.manage', 'creative.approve');

        return $user;
    }

    private function source(string $review = TryonResult::REVIEW_APPROVED): TryonResult
    {
        $product   = Product::factory()->create();
        $mannequin = Mannequin::create([
            'name' => 'Test Manken', 'status' => Mannequin::STATUS_READY,
            'reference_image_path' => 'mannequins/1/reference.png',
            'review_status' => Mannequin::REVIEW_APPROVED,
        ]);
        $pose = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        Storage::disk(Media::disk())->put("products/{$product->id}/onmodel_staged_1.png", UploadedFile::fake()->image('a.png')->getContent());
        Storage::disk(Media::disk())->put("products/{$product->id}/photo.png", UploadedFile::fake()->image('p.png')->getContent());
        $product->images()->create(['path' => "products/{$product->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        return TryonResult::create([
            'product_id' => $product->id, 'mannequin_id' => $mannequin->id, 'pose_id' => $pose->id,
            'status' => TryonResult::STATUS_DONE, 'review_status' => $review,
            'staged_image_path' => "products/{$product->id}/onmodel_staged_1.png",
        ]);
    }

    public function test_closeups_are_queued_from_approved_full_body_result(): void
    {
        Queue::fake();
        $source = $this->source();

        $this->actingAs($this->user())
            ->post("/creative/tryon/{$source->id}/closeups", ['shots' => ['hem', 'fabric']])
            ->assertRedirect()->assertSessionHas('success');

        $closeups = TryonResult::query()->where('source_result_id', $source->id)->get();
        $this->assertEqualsCanonicalizing(['hem', 'fabric'], $closeups->pluck('shot')->all());
        $this->assertTrue($closeups->every(fn ($r) => $r->pose_id === $source->pose_id && $r->status === TryonResult::STATUS_QUEUED));
        $this->assertSame(TryonResult::SHOT_FULL, $source->fresh()->shot);
        Queue::assertPushed(GenerateOnModelJob::class, 2);
    }

    public function test_unapproved_source_is_refused(): void
    {
        Queue::fake();
        $source = $this->source(TryonResult::REVIEW_PENDING);

        $this->actingAs($this->user())
            ->post("/creative/tryon/{$source->id}/closeups", ['shots' => ['hem']])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, TryonResult::query()->where('source_result_id', $source->id)->count());
        Queue::assertNotPushed(GenerateOnModelJob::class);
    }

    public function test_unknown_shot_is_rejected(): void
    {
        $source = $this->source();

        $this->actingAs($this->user())
            ->post("/creative/tryon/{$source->id}/closeups", ['shots' => ['selfie']])
            ->assertSessionHasErrors('shots.0');
    }

    public function test_requires_asset_manage_permission(): void
    {
        $source = $this->source();
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        $this->actingAs($user)->post("/creative/tryon/{$source->id}/closeups", ['shots' => ['hem']])->assertForbidden();
    }

    public function test_job_generates_closeup_which_then_publishes_through_normal_approval(): void
    {
        // Python/AI adımları devre dışı: mock yakın plan + passthrough hazırlık/iyileştirme.
        $this->app->bind(CloseupComposerContract::class, MockCloseupComposer::class);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $path): string { return $path; }
        });
        $this->app->instance(ImageEnhancerContract::class, new class implements ImageEnhancerContract {
            public function enhance(string $path): string { return $path; }
        });

        $source = $this->source();
        $closeup = app(ProductCloseupService::class)->queue($source, ['fabric'])->first();

        (new GenerateOnModelJob($closeup->id, $closeup->generation_token))->handle(
            app(\Modules\Creative\Services\ProductOnModelService::class),
            app(ProductCloseupService::class),
        );

        $closeup->refresh();
        $this->assertSame(TryonResult::STATUS_DONE, $closeup->status, (string) $closeup->error);
        $this->assertSame(TryonResult::REVIEW_PENDING, $closeup->review_status);
        Storage::disk(Media::disk())->assertExists($closeup->staged_image_path);
        // Kaynak giydirme dosyası ve ürün fotoğrafı silinmemeli (yalnız geçici dosyalar temizlenir).
        Storage::disk(Media::disk())->assertExists($source->staged_image_path);
        Storage::disk(Media::disk())->assertExists("products/{$source->product_id}/photo.png");

        $this->actingAs($this->user())->post("/creative/tryon/{$closeup->id}/approve")->assertRedirect();

        $closeup->refresh();
        $this->assertSame(TryonResult::REVIEW_APPROVED, $closeup->review_status);
        $this->assertNotNull($closeup->product_image_id);
        $this->assertStringContainsString('Kumaş dokusu', $closeup->productImage->alt_text);
    }
}
