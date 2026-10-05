<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Drivers\Fal\FalFashnTryOn;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOn;
use Modules\Creative\Services\Ai\Drivers\Mock\MockTryOn;
use Modules\Creative\Services\Ai\TryOnDriverResolver;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün Giydirme sayfasındaki Gemini / fal.ai seçimi: istekle gelen tryon_driver
 * meta.tryon_driver_requested'e yazılır, TryOnDriverResolver de üretimde onu kullanır.
 */
class TryonDriverSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);
    }

    private function postTryon(array $extra = [])
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.asset.manage');

        $product   = Product::factory()->create();
        $mannequin = Mannequin::create([
            'name' => 'Test Manken', 'status' => Mannequin::STATUS_READY,
            'reference_image_path' => 'mannequins/1/reference.png',
            'review_status' => Mannequin::REVIEW_APPROVED,
        ]);
        $pose = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        return [$this->actingAs($user)->post('/creative/tryon', [
            'product_id'    => $product->id,
            'mannequin_id'  => $mannequin->id,
            'pose_ids'      => [$pose->id],
            'garment_image' => UploadedFile::fake()->image('garment.jpg'),
            ...$extra,
        ]), $product, $pose];
    }

    public function test_selected_driver_is_stored_on_result_meta(): void
    {
        [$response, $product, $pose] = $this->postTryon(['tryon_driver' => 'gemini']);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $result = TryonResult::query()->where('product_id', $product->id)->where('pose_id', $pose->id)->firstOrFail();
        $this->assertSame('gemini', $result->meta['tryon_driver_requested'] ?? null);
    }

    public function test_unknown_driver_is_rejected(): void
    {
        [$response] = $this->postTryon(['tryon_driver' => 'openai']);
        $response->assertSessionHasErrors('tryon_driver');
    }

    public function test_resolver_builds_requested_driver_and_falls_back_to_mock_without_key(): void
    {
        config([
            'creative.ai.tryon_driver' => 'fal',
            'creative.ai.fal.key' => 'x',
            'creative.ai.fal.model' => 'fal-ai/fashn/tryon/v1.6',
            'creative.ai.gemini.api_key' => 'y',
        ]);
        $resolver = app(TryOnDriverResolver::class);

        $this->assertInstanceOf(GeminiTryOn::class, $resolver->make('gemini'));
        $this->assertInstanceOf(FalFashnTryOn::class, $resolver->make('fal'));
        $this->assertInstanceOf(FalFashnTryOn::class, $resolver->make(null));

        config(['creative.ai.gemini.api_key' => '']);
        $this->assertInstanceOf(MockTryOn::class, $resolver->make('gemini'));
    }

    public function test_default_driver_keeps_container_binding(): void
    {
        $fake = \Mockery::mock(GarmentTryOnContract::class);
        $this->app->instance(GarmentTryOnContract::class, $fake);

        $this->assertSame($fake, app(TryOnDriverResolver::class)->make(null));
    }
}
