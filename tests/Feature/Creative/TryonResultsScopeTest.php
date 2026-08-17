<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * /creative/tryon/results, seçilen ürüne ait giydirme geçmişini diğer
 * ürünlerin kayıtlarından izole eder — bkz. TryonController::results().
 */
class TryonResultsScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
    }

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        return $user;
    }

    private function tryonFor(Product $product): TryonResult
    {
        $pose = Pose::create(['pose_key' => 'stand-'.$product->id, 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        $staged = sprintf('products/%d/onmodel_staged_test.png', $product->id);
        Storage::disk('public')->put($staged, 'fake-bytes');

        return TryonResult::create([
            'product_id'        => $product->id,
            'pose_id'           => $pose->id,
            'status'            => TryonResult::STATUS_DONE,
            'staged_image_path' => $staged,
            'review_status'     => TryonResult::REVIEW_PENDING,
        ]);
    }

    public function test_results_endpoint_only_returns_the_requested_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $resultA = $this->tryonFor($productA);
        $this->tryonFor($productB);

        $response = $this->actingAs($this->viewer())
            ->getJson('/creative/tryon/results?product_id='.$productA->id)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$resultA->id], $ids);
    }

    public function test_results_endpoint_requires_a_valid_product_id(): void
    {
        $this->actingAs($this->viewer())
            ->getJson('/creative/tryon/results')
            ->assertStatus(422);
    }

    public function test_index_global_feed_still_includes_every_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $resultA = $this->tryonFor($productA);
        $resultB = $this->tryonFor($productB);

        $response = $this->actingAs($this->viewer())
            ->get('/creative/tryon')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['results'])->pluck('id')->all();

        $this->assertContains($resultA->id, $ids);
        $this->assertContains($resultB->id, $ids);
    }
}
