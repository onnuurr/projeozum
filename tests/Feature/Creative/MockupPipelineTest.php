<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMockupGenerator;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupProductTypeResolver;
use Modules\Creative\Services\Mockup\MockupTryOnService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductAttributeOption;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mokap giydirme hattı (Faz 1): bölge/boy çözümleme, kombin → baz görseller → giydirme →
 * onay kuyruğu. AI (Gemini) ve görüntü (Python) adımları sahte sürücülerle; gerçek çağrı yapılmaz.
 */
class MockupPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Storage::fake('local');
        config(['creative.ai.gemini.api_key' => '', 'creative.mockup.work_disk' => 'local']);
        foreach (['creative.view', 'creative.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->app->bind(MockupGeneratorContract::class, MockMockupGenerator::class);
        FakeMockupVision::$shoeSkin = [];
        $this->app->instance(MockupVisionContract::class, new FakeMockupVision);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $sourcePath): string { return $sourcePath; }
        });
    }

    private function png(string $rel): void
    {
        Storage::disk(Media::disk())->put($rel, UploadedFile::fake()->image('x.png', 60, 80)->getContent());
    }

    private function product(string $category = 'Etek', ?string $boy = 'Kısa / Mini'): Product
    {
        $cat = Category::create(['name' => $category, 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        // Ayarlar › Mokap eşleştirmesi (canlıda migration eski config'ten taşıdı).
        $map = ['Etek' => ['bottom', null], 'Kot Pantolon' => ['bottom', 'maxi'], 'Takım' => ['set', null]][$category] ?? null;
        if ($map) {
            \Modules\Creative\Models\CategoryRegion::create(['category_id' => $cat->id, 'region' => $map[0], 'length' => $map[1]]);
        }
        $attrs = [];
        if ($boy !== null) {
            $attr = ProductAttribute::firstOrCreate(['code' => 'boy'], ['name' => 'Boy', 'type' => 'select']);
            $attrs['boy'] = ProductAttributeOption::firstOrCreate(['product_attribute_id' => $attr->id, 'name' => $boy])->id;
        }
        $product = Product::factory()->create(['category_id' => $cat->id, 'attributes' => $attrs]);
        $this->png("products/{$product->id}/photo.png");
        $product->images()->create(['path' => "products/{$product->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        return $product;
    }

    private function mannequinAndPoses(): Mannequin
    {
        $this->png('mannequins/1/reference.png');
        foreach (['standing_front', 'walking_stride', 'standing_three_qtr', 'seated_floor'] as $i => $key) {
            $this->png("mannequins/poses/{$key}.png");
            Pose::create(['pose_key' => $key, 'label' => $key, 'prompt' => "pose {$key}", 'status' => Pose::STATUS_READY,
                'mockup_regions' => $key === 'seated_floor' ? null : ['bottom', 'top', 'one_piece', 'set'],
                'sort_order' => $i, 'preview_image_path' => "mannequins/poses/{$key}.png"]);
        }

        return Mannequin::create(['name' => 'M', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED,
            'reference_image_path' => 'mannequins/1/reference.png']);
    }

    public function test_stylist_picks_from_option_list_and_varies_combos_within_a_category(): void
    {
        Queue::fake();
        $mannequin = $this->mannequinAndPoses();
        $pipeline = app(MockupPipelineService::class);
        $first = $this->product();
        $cat = $first->category_id;

        $a = $pipeline->run($pipeline->start($first, $mannequin));
        $this->assertSame(\Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder::STYLIST_VERSION, (int) $a->stylist_prompt_version);
        // Parçalar seçenek anahtarı; cümle seçeneklerin prompt_en metinlerinden (AI metni değil).
        $this->assertSame('fitted_rib_tee', $a->pieces['top']);
        $this->assertStringStartsWith('A fitted short-sleeve ribbed cotton t-shirt in ', $a->outfit_sentence);
        $this->assertStringContainsString('fully tucked in', $a->outfit_sentence);
        $this->assertStringNotContainsString('mock', $a->outfit_sentence);
        $this->assertSame(0.9, $a->meta['stylist_confidence']);

        // Aynı kategoride ikinci ürün: aynı stilist önerisi gelse de (sahte sürücü hep aynı) ikili tekrarlanmaz.
        $second = $this->product();
        $second->update(['category_id' => $cat]);
        $b = $pipeline->run($pipeline->start($second->fresh(), $mannequin));
        $this->assertNotSame([$a->pieces['top'], $a->pieces['footwear']], [$b->pieces['top'], $b->pieces['footwear']]);
        $this->assertStringContainsString('Çeşitlilik', implode(' ', $b->meta['stylist_warnings']));
    }

    public function test_resolver_reads_region_from_category_and_length_from_boy_attribute(): void
    {
        $r = app(MockupProductTypeResolver::class);

        $this->assertSame(['region' => 'bottom', 'length' => 'mini', 'length_source' => 'attribute'], $r->resolve($this->product('Etek', 'Kısa / Mini')));
        $this->assertSame(['region' => 'bottom', 'length' => 'maxi', 'length_source' => 'category'], $r->resolve($this->product('Kot Pantolon', null)));
        $this->assertSame(['region' => 'set', 'length' => null, 'length_source' => 'ai'], $r->resolve($this->product('Takım', 'Belirtilmemiş')));
        $this->assertSame('override', $r->resolve($this->product('Etek'), null, 'midi')['length_source']);
    }

    public function test_outerwear_category_is_outside_mockup(): void
    {
        $this->expectException(\RuntimeException::class);
        app(MockupProductTypeResolver::class)->resolve($this->product('Ceket', null));
    }

    public function test_start_creates_outfit_and_queues_job(): void
    {
        Queue::fake();
        $outfit = app(MockupPipelineService::class)->start($this->product(), $this->mannequinAndPoses());

        $this->assertSame('bottom', $outfit->region);
        $this->assertSame('mini', $outfit->length);
        Queue::assertPushed(GenerateMockupJob::class, fn ($j) => $j->outfitId === $outfit->id);
    }

    public function test_full_pipeline_lands_in_review_queue_without_touching_legacy_results(): void
    {
        Queue::fake();
        $product = $this->product();
        $mannequin = $this->mannequinAndPoses();
        $front = Pose::where('pose_key', 'standing_front')->first();

        // Aynı ürün+pozda eski hat (referans) sonucu — mokap bunu ezmemeli.
        $legacy = TryonResult::create(['product_id' => $product->id, 'mannequin_id' => $mannequin->id, 'pose_id' => $front->id,
            'status' => TryonResult::STATUS_DONE, 'review_status' => TryonResult::REVIEW_APPROVED, 'staged_image_path' => 'legacy.png']);

        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($product, $mannequin));

        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->status);
        $this->assertNotEmpty($outfit->outfit_sentence);
        // Ana kare + 2 ek poz; mokap dışı poz (seated_floor) stilistin listesine hiç girmez.
        $this->assertEqualsCanonicalizing(['walking_stride', 'standing_three_qtr'], $outfit->poses);
        $bases = MockupBase::where('outfit_id', $outfit->id)->get();
        $this->assertCount(3, $bases);
        $this->assertTrue($bases->every(fn ($b) => $b->status === MockupBase::STATUS_READY && $b->masks['crop'] !== null));
        $this->assertNull($bases->firstWhere('pose_id', $front->id)->derived_from_base_id);
        $this->assertCount(2, $bases->whereNotNull('derived_from_base_id'));
        Storage::disk('local')->assertExists($bases->first()->image_path);

        $results = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->get();
        $this->assertCount(3, $results);
        Queue::assertPushed(GenerateOnModelJob::class, 3);

        // Giydirme işi mokap satırını MockupTryOnService'e yönlendirir → onay kuyruğu.
        $r = $results->firstWhere('pose_id', $front->id);
        (new GenerateOnModelJob($r->id, $r->generation_token))->handle(
            app(\Modules\Creative\Services\ProductOnModelService::class),
            app(\Modules\Creative\Services\ProductCloseupService::class),
            app(MockupTryOnService::class),
        );
        $r->refresh();
        $this->assertSame(TryonResult::STATUS_DONE, $r->status, (string) $r->error);
        $this->assertSame(TryonResult::REVIEW_PENDING, $r->review_status);
        $this->assertEquals(1.0, $r->qa['composite']['outside_unchanged']);  // JSON 1.0 → 1
        Storage::disk(Media::disk())->assertExists($r->staged_image_path);

        $legacy->refresh();
        $this->assertSame(TryonResult::REVIEW_APPROVED, $legacy->review_status);
        $this->assertSame('legacy.png', $legacy->staged_image_path);
    }

    public function test_approving_mockup_result_publishes_to_product_gallery(): void
    {
        Queue::fake();
        $pipeline = app(MockupPipelineService::class);
        $product = $this->product();
        $pipeline->run($pipeline->start($product, $this->mannequinAndPoses()));
        $r = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->first();
        app(MockupTryOnService::class)->generate($r, $r->generation_token);

        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.approve');
        $this->actingAs($user)->post("/creative/tryon/{$r->id}/approve")->assertRedirect();

        $this->assertNotNull($r->fresh()->product_image_id);
        $this->assertSame(2, $product->images()->count());
    }

    public function test_garment_path_is_normalized_and_validated_before_creating_outfit(): void
    {
        Queue::fake();
        $pipeline = app(MockupPipelineService::class);
        $mannequin = $this->mannequinAndPoses();
        $product = $this->product();
        $abs = base_path('tests/Feature/Creative/__mockup_garment.png');
        file_put_contents($abs, UploadedFile::fake()->image('g.png', 10, 10)->getContent());

        try {
            // Başındaki "/" unutulmuş mutlak yol ve proje kökünden göreli yol → mutlak yola çevrilir.
            $this->assertSame($abs, $pipeline->start($product, $mannequin, garmentPath: ltrim($abs, '/'))->meta['garment_path']);
            $this->assertSame($abs, $pipeline->start($product, $mannequin, garmentPath: 'tests/Feature/Creative/__mockup_garment.png')->meta['garment_path']);
            // Medya diskindeki yol olduğu gibi kalır.
            $this->assertSame("products/{$product->id}/photo.png", $pipeline->start($product, $mannequin, garmentPath: "products/{$product->id}/photo.png")->meta['garment_path']);
        } finally {
            @unlink($abs);
        }

        $before = MockupOutfit::count();
        try {
            $pipeline->start($product, $mannequin, garmentPath: 'yok/boyle-bir-dosya.webp');
            $this->fail('istisna bekleniyordu');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Giysi görseli bulunamadı', $e->getMessage());
        }
        $this->assertSame($before, MockupOutfit::count());
    }

    public function test_outfit_is_processed_once_even_if_run_twice_and_sync_start_does_not_dispatch(): void
    {
        Queue::fake();
        $counter = new class extends MockMockupGenerator {
            public static int $stylistCalls = 0;
            public function __construct() { parent::__construct(app(\Modules\Creative\Services\Ai\Drivers\Mock\MockImageFactory::class)); }
            public function stylist(string $productImagePath, array $context): array { static::$stylistCalls++; return parent::stylist($productImagePath, $context); }
        };
        $counter::$stylistCalls = 0;
        $this->app->instance(MockupGeneratorContract::class, $counter);
        $pipeline = app(MockupPipelineService::class);

        $outfit = $pipeline->start($this->product(), $this->mannequinAndPoses(), dispatch: false);
        Queue::assertNotPushed(GenerateMockupJob::class);

        $pipeline->run($outfit);
        $pipeline->run($outfit->fresh());          // ikinci kopya: hiçbir şey yapmamalı
        (new GenerateMockupJob($outfit->id))->handle($pipeline);

        $this->assertSame(1, $counter::$stylistCalls);
        $this->assertSame(1, MockupBase::where('outfit_id', $outfit->id)->max('attempts'));
        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->fresh()->status);
    }

    public function test_failed_master_marks_outfit_failed(): void
    {
        Queue::fake();
        $this->app->instance(MockupVisionContract::class, new class extends FakeMockupVision {
            public function segment(string $imagePath, string $labelsPath): array { throw new \RuntimeException('segment patladı'); }
        });
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->start($this->product(), $this->mannequinAndPoses());

        try {
            $pipeline->run($outfit);
            $this->fail('istisna bekleniyordu');
        } catch (\RuntimeException) {
        }

        $this->assertSame(MockupOutfit::STATUS_FAILED, $outfit->fresh()->status);
        $this->assertStringContainsString('segment patladı', (string) $outfit->fresh()->error);
        $this->assertSame(0, TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->count());
    }
}
