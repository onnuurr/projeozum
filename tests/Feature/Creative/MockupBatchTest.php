<?php

namespace Tests\Feature\Creative;

use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\AiBatchRequest;
use Modules\Creative\Models\AiUsage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMockupGenerator;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupTryOnService;
use Modules\Creative\Services\ProductCloseupService;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mokap görselleri Gemini Batch API ile: görsel çağrısı anlık yapılmaz, kuyruğa yazılır; zamanlayıcı
 * (creative:ai-batch) gönderir, toplar, işi sürdürür; iş kaldığı yerden devam eder. Gemini HTTP sahte.
 */
class MockupBatchTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,int> batch adı → gönderilen istek sayısı */
    private array $batches = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Storage::fake('local');
        config(['creative.ai.gemini.api_key' => 'test-key', 'creative.mockup.work_disk' => 'local', 'creative.mockup.batch' => true]);
        foreach (['creative.view', 'creative.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->app->bind(MockupGeneratorContract::class, GeminiMockupGenerator::class);
        FakeMockupVision::$shoeSkin = [];
        $this->app->instance(MockupVisionContract::class, new FakeMockupVision);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $sourcePath): string { return $sourcePath; }
        });

        $png = base64_encode(UploadedFile::fake()->image('o.png', 60, 80)->getContent());
        $image = ['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]],
            'usageMetadata' => ['promptTokenCount' => 2000, 'candidatesTokenCount' => 1120]];
        // Stilist ve kontrol cevapları aynı sahte analizden: kombin + "pass".
        $analysis = json_encode(['product_summary' => 'denim skirt', 'outfit_sentence' => 'A white tee and sneakers.',
            'poses' => ['walking_stride', 'standing_three_qtr'], 'reasoning_tr' => 'Test.', 'verdict' => 'pass', 'issues' => []]);

        Http::fake(function (Request $request) use ($image, $analysis) {
            $url = $request->url();
            if (str_contains($url, ':batchGenerateContent')) {
                $name = 'batches/b' . (count($this->batches) + 1);
                $this->batches[$name] = count($request['batch']['input_config']['requests']['requests']);

                return Http::response(['name' => $name, 'metadata' => ['state' => 'BATCH_STATE_PENDING']]);
            }
            if (preg_match('#/(batches/b\d+)$#', $url, $m)) {
                $keys = AiBatchRequest::where('batch_name', $m[1])->orderBy('id')->pluck('id');

                return Http::response(['name' => $m[1], 'done' => true, 'metadata' => ['state' => 'BATCH_STATE_SUCCEEDED'],
                    'response' => ['inlinedResponses' => ['inlinedResponses' => $keys->map(fn ($k) => ['response' => $image, 'metadata' => ['key' => (string) $k]])->all()]]]);
            }
            if (str_contains($url, ':generateContent') && isset($request['generationConfig']['responseModalities'])) {
                return Http::response(['error' => ['message' => 'görsel çağrısı anlık yapılmamalıydı']], 500);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $analysis]]]]], 'usageMetadata' => ['promptTokenCount' => 500, 'candidatesTokenCount' => 100]]);
        });
    }

    private function png(string $rel): void
    {
        Storage::disk(Media::disk())->put($rel, UploadedFile::fake()->image('x.png', 60, 80)->getContent());
    }

    private function setUpProduct(): array
    {
        $cat = Category::create(['name' => 'Etek', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        \Modules\Creative\Models\CategoryRegion::create(['category_id' => $cat->id, 'region' => 'bottom']);
        $product = Product::factory()->create(['category_id' => $cat->id, 'attributes' => []]);
        $this->png("products/{$product->id}/photo.png");
        $product->images()->create(['path' => "products/{$product->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        $this->png('mannequins/1/reference.png');
        foreach (['standing_front', 'walking_stride', 'standing_three_qtr'] as $i => $key) {
            $this->png("mannequins/poses/{$key}.png");
            Pose::create(['pose_key' => $key, 'label' => $key, 'prompt' => "pose {$key}", 'status' => Pose::STATUS_READY,
                'mockup_regions' => $key === 'seated_floor' ? null : ['bottom', 'top', 'one_piece', 'set', 'outerwear'],
                'sort_order' => $i, 'preview_image_path' => "mannequins/poses/{$key}.png"]);
        }
        $mannequin = Mannequin::create(['name' => 'M', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED,
            'reference_image_path' => 'mannequins/1/reference.png']);

        return [$product, $mannequin];
    }

    private function tick(): void
    {
        $this->artisan('creative:ai-batch')->assertSuccessful();
    }

    private function runTryon(TryonResult $r): TryonResult
    {
        (new GenerateOnModelJob($r->id, $r->generation_token))->handle(
            app(ProductOnModelService::class), app(ProductCloseupService::class), app(MockupTryOnService::class),
        );

        return $r->fresh();
    }

    public function test_mockup_images_go_through_batch_and_the_flow_resumes_where_it_left_off(): void
    {
        Queue::fake();
        [$product, $mannequin] = $this->setUpProduct();
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->start($product, $mannequin, length: 'mini', dispatch: false, batch: true);

        // 1) Stilist anlık; ana kare kuyruğa yazılır, akış bekler.
        $outfit = $pipeline->run($outfit);
        $this->assertSame(MockupOutfit::STATUS_GENERATING, $outfit->status);
        $this->assertSame(1, AiBatchRequest::where('status', AiBatchRequest::STATUS_QUEUED)->count());
        $sentence = $outfit->outfit_sentence;

        // 2) Zamanlayıcı: gönder + topla → iş yeniden kuyrukta; maliyet yarı fiyat.
        $this->tick();
        $this->assertSame(['batches/b1' => 1], $this->batches);
        Queue::assertPushed(GenerateMockupJob::class, fn ($j) => $j->outfitId === $outfit->id);
        $usage = AiUsage::where('operation', 'image_batch')->sole();
        $this->assertEqualsWithDelta((0.067 + 2000 * 0.5 / 1e6) * 0.5, $usage->est_cost_usd, 0.00001);

        // 3) Devam: ana kare hazır; iki türetilen poz TEK batch'te gider. Stilist yeniden çağrılmaz.
        $outfit = $pipeline->run($outfit);
        $this->assertSame($sentence, $outfit->outfit_sentence);
        $this->assertSame(MockupBase::STATUS_READY, MockupBase::where('outfit_id', $outfit->id)->whereNull('derived_from_base_id')->sole()->status);
        $this->tick();
        $this->assertSame(2, $this->batches['batches/b2']);

        // 4) Devam: bazlar hazır → giydirme işleri kuyrukta, kombin hazır.
        $outfit = $pipeline->run($outfit);
        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->status);
        $results = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->get();
        $this->assertCount(3, $results);
        $this->assertTrue($results->every(fn ($r) => $r->meta['batch'] === true));

        // 5) Giydirme: kuyruğa yazılır (sonuç beklemede), batch sonrası iş devam edip onaya düşer.
        $r = $this->runTryon($results->first());
        $this->assertSame(TryonResult::STATUS_GENERATING, $r->status);
        $this->assertSame(0, $r->meta['mockup_run']['attempt']);
        $current = fn ($j) => $j->resultId === $r->id && $j->generationToken === $r->generation_token;
        $this->assertCount(1, Queue::pushed(GenerateOnModelJob::class, $current));   // ilk kuyruğa alma
        $this->tick();
        $this->assertCount(2, Queue::pushed(GenerateOnModelJob::class, $current));   // + batch sonrası devam

        $r = $this->runTryon($r);
        $this->assertSame(TryonResult::STATUS_DONE, $r->status, (string) $r->error);
        $this->assertSame(TryonResult::REVIEW_PENDING, $r->review_status);
        $this->assertArrayNotHasKey('mockup_run', $r->meta);
        Storage::disk(Media::disk())->assertExists($r->staged_image_path);

        // Hiçbir görsel çağrısı anlık yapılmadı; 4 görsel = 4 batch isteği, hepsi başarılı.
        Http::assertNotSent(fn (Request $req) => str_contains($req->url(), ':generateContent') && isset($req['generationConfig']['responseModalities']));
        $this->assertSame(4, AiBatchRequest::where('status', AiBatchRequest::STATUS_SUCCEEDED)->count());
    }

    private function stopper(): \App\Models\User
    {
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);
        $u = \App\Models\User::factory()->create();
        $u->givePermissionTo('creative.asset.manage');

        return $u;
    }

    public function test_stopping_a_product_halts_its_mockup_and_cancels_pending_ai_requests(): void
    {
        Queue::fake();
        [$product, $mannequin] = $this->setUpProduct();
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($product, $mannequin, length: 'mini', dispatch: false, batch: true));
        app(\Modules\Creative\Services\AiBatchService::class)->submitQueued();          // ana kare Google'da (batches/b1)
        $other = TryonResult::create(['product_id' => Product::factory()->create()->id, 'mannequin_id' => $mannequin->id,
            'pose_id' => Pose::first()->id, 'status' => TryonResult::STATUS_QUEUED]);

        $this->actingAs($this->stopper())->post('/creative/tryon/stop', ['product_id' => $product->id])->assertSessionHas('success');

        $this->assertSame(MockupOutfit::STATUS_FAILED, $outfit->fresh()->status);
        $this->assertSame(MockupBase::STATUS_FAILED, MockupBase::where('outfit_id', $outfit->id)->sole()->status);
        $this->assertSame([AiBatchRequest::STATUS_FAILED], AiBatchRequest::pluck('status')->unique()->values()->all());
        Http::assertSent(fn (Request $req) => str_ends_with($req->url(), 'batches/b1:cancel'));
        $this->assertSame(TryonResult::STATUS_QUEUED, $other->fresh()->status);                 // başka ürün etkilenmez

        // Sonradan gelen toplama hiçbir şeyi sürdürmez.
        $this->tick();
        Queue::assertNotPushed(GenerateMockupJob::class);
    }

    public function test_stopped_result_ignores_its_already_queued_job_and_nothing_to_stop_is_reported(): void
    {
        Queue::fake();
        [$product, $mannequin] = $this->setUpProduct();
        $r = TryonResult::create(['product_id' => $product->id, 'mannequin_id' => $mannequin->id, 'pose_id' => Pose::first()->id,
            'status' => TryonResult::STATUS_QUEUED, 'generation_token' => 'old-token']);

        $this->actingAs($this->stopper())->post("/creative/tryon/{$r->id}/stop")->assertSessionHas('success');
        (new GenerateOnModelJob($r->id, 'old-token'))->handle(app(ProductOnModelService::class), app(ProductCloseupService::class), app(MockupTryOnService::class));

        $r->refresh();
        $this->assertSame(TryonResult::STATUS_FAILED, $r->status);
        $this->assertSame(\Modules\Creative\Services\GenerationStopService::REASON, $r->error);
        $this->actingAs($this->stopper())->post('/creative/tryon/stop')->assertSessionHas('error', __('creative::messages.stop.nothing'));
    }

    public function test_stopping_requires_the_asset_manage_permission(): void
    {
        $u = \App\Models\User::factory()->create();
        $u->givePermissionTo('creative.view');
        $this->actingAs($u)->post('/creative/tryon/stop')->assertForbidden();
    }

    public function test_failed_batch_request_fails_the_base_with_a_readable_reason(): void
    {
        Queue::fake();
        [$product, $mannequin] = $this->setUpProduct();
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($product, $mannequin, length: 'mini', dispatch: false, batch: true));

        AiBatchRequest::query()->update(['status' => AiBatchRequest::STATUS_FAILED, 'error' => 'blocked']);
        try {
            $pipeline->run($outfit);
        } catch (\RuntimeException) {
        }
        $outfit->refresh();

        $this->assertSame(MockupOutfit::STATUS_FAILED, $outfit->status);
        $this->assertStringContainsString(__('creative::messages.ai_provider.blocked'), (string) $outfit->error);
    }

    public function test_now_mode_calls_gemini_directly_and_queues_nothing(): void
    {
        Queue::fake();
        [$product, $mannequin] = $this->setUpProduct();
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->start($product, $mannequin, length: 'mini', dispatch: false, batch: false);

        // Sahte HTTP anlık görsel çağrısına 500 döner: denendiğini görmek yeter (ana kare başarısız olur).
        try {
            $pipeline->run($outfit);
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, AiBatchRequest::count());
        Http::assertSent(fn (Request $req) => str_contains($req->url(), ':generateContent') && isset($req['generationConfig']['responseModalities']));
    }
}
