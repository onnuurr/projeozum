<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockImageFactory;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMockupGenerator;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupTryOnService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CountingDetailGenerator extends MockMockupGenerator
{
    public static int $detailCalls = 0;

    public function __construct() { parent::__construct(app(MockImageFactory::class)); }

    public function detailBox(array $photoPaths): array
    {
        self::$detailCalls++;

        return ['photo' => count($photoPaths) - 1, 'box' => [0.4, 0.3, 0.6, 0.5], 'label_tr' => 'fiyonk'];
    }
}

/**
 * Köşe detayı: yalnız kombinin ilk ek pozuna, ürün fotoğrafından (AI kutusu) işlenir; ana kareye işlenmez.
 * Onaycı kutuyu düzeltebilir / detayı kaldırabilir (temiz görselden, yeni AI çağrısı yok).
 */
class MockupDetailInsetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Storage::fake('local');
        config(['creative.ai.gemini.api_key' => '', 'creative.mockup.work_disk' => 'local', 'creative.mockup.qa.enabled' => false, 'creative.mockup.extra_poses' => 1]);
        foreach (['creative.view', 'creative.approve', 'creative.asset.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        CountingDetailGenerator::$detailCalls = 0;
        FakeMockupVision::$insets = [];
        $this->app->instance(MockupGeneratorContract::class, new CountingDetailGenerator);
        $this->app->instance(MockupVisionContract::class, new FakeMockupVision);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $sourcePath): string { return $sourcePath; }
        });
        Queue::fake();
    }

    private function png(string $rel): void
    {
        Storage::disk(Media::disk())->put($rel, UploadedFile::fake()->image('x.png', 60, 80)->getContent());
    }

    private function product(): Product
    {
        $this->png('mannequins/1/reference.png');
        foreach (['standing_front', 'walking_stride'] as $i => $key) {
            $this->png("mannequins/poses/{$key}.png");
            Pose::create(['pose_key' => $key, 'label' => $key, 'prompt' => "pose {$key}", 'status' => Pose::STATUS_READY,
                'mockup_regions' => ['bottom', 'top', 'one_piece', 'set', 'outerwear'], 'sort_order' => $i, 'preview_image_path' => "mannequins/poses/{$key}.png"]);
        }
        $cat = Category::create(['name' => 'Etek', 'slug' => 'e-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        \Modules\Creative\Models\CategoryRegion::create(['category_id' => $cat->id, 'region' => 'bottom']);
        $product = Product::factory()->create(['category_id' => $cat->id]);
        foreach (['photo', 'closeup'] as $i => $name) {
            $this->png("products/{$product->id}/{$name}.png");
            $product->images()->create(['path' => "products/{$product->id}/{$name}.png", 'sort_order' => $i, 'is_cover' => $i === 0]);
        }

        return $product;
    }

    private function generateAll(Product $product): void
    {
        $mannequin = Mannequin::create(['name' => 'M', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED, 'reference_image_path' => 'mannequins/1/reference.png']);
        $pipeline = app(MockupPipelineService::class);
        $pipeline->run($pipeline->start($product, $mannequin, length: 'mini', dispatch: false));
        foreach (TryonResult::where('pipeline', 'mockup')->get() as $r) {
            app(MockupTryOnService::class)->generate($r, $r->generation_token);
        }
    }

    private function resultFor(string $poseKey): TryonResult
    {
        return TryonResult::where('pipeline', 'mockup')->where('pose_id', Pose::where('pose_key', $poseKey)->value('id'))->firstOrFail();
    }

    public function test_inset_goes_only_on_the_first_extra_pose_from_the_ai_chosen_photo(): void
    {
        $product = $this->product();
        $this->generateAll($product);

        $this->assertArrayNotHasKey('detail_inset', (array) $this->resultFor('standing_front')->meta);
        $inset = $this->resultFor('walking_stride')->meta['detail_inset'];
        $this->assertSame("products/{$product->id}/closeup.png", $inset['photo']);      // AI'nin seçtiği (yakın) fotoğraf
        $this->assertSame([0.4, 0.3, 0.6, 0.5], $inset['box']);
        $this->assertSame('ai', $inset['source']);
        $this->assertSame(1, CountingDetailGenerator::$detailCalls);
        $this->assertCount(1, FakeMockupVision::$insets);
    }

    public function test_reviewer_can_fix_the_box_or_remove_the_inset_without_a_new_ai_call(): void
    {
        $product = $this->product();
        $this->generateAll($product);
        $r = $this->resultFor('walking_stride');
        foreach (['creative.view', 'creative.asset.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['creative.view', 'creative.asset.manage']);

        $this->actingAs($user)->put("/creative/tryon/{$r->id}/inset", ['photo' => "products/{$product->id}/photo.png", 'box' => [0.1, 0.1, 0.3, 0.3]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $inset = $r->fresh()->meta['detail_inset'];
        $this->assertSame('manual', $inset['source']);
        $this->assertSame([0.1, 0.1, 0.3, 0.3], $inset['box']);

        $this->actingAs($user)->put("/creative/tryon/{$r->id}/inset", ['photo' => 'products/999/other.png', 'box' => [0.1, 0.1, 0.3, 0.3]])
            ->assertSessionHas('error');                                                    // başka ürünün fotoğrafı

        $this->actingAs($user)->put("/creative/tryon/{$r->id}/inset", ['off' => true])->assertRedirect();
        $this->assertTrue($r->fresh()->meta['detail_inset']['off']);
        $this->assertSame(1, CountingDetailGenerator::$detailCalls);

        // Yeniden üretimde onaycının kararı (kaldırma) korunur.
        app(MockupTryOnService::class)->generate($r->fresh(), $r->fresh()->generation_token);
        $this->assertTrue($r->fresh()->meta['detail_inset']['off']);
    }
}
