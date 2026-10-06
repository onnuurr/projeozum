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
 * /creative/tryon/product-summaries — sonuç galerisinin ürün listesi (bkz. TryonController::productSummaries()).
 */
class TryonProductSummariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
    }

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        return $user;
    }

    private function makeResult(Product $product, string $status, ?string $review = null): TryonResult
    {
        $pose = Pose::firstOrCreate(['pose_key' => 'stand'], ['label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        return TryonResult::create([
            'product_id'        => $product->id,
            'pose_id'           => $pose->id,
            'status'            => $status,
            'staged_image_path' => $status === TryonResult::STATUS_DONE ? "products/{$product->id}/onmodel_staged_t.png" : null,
            'review_status'     => $review,
        ]);
    }

    public function test_counts_per_product_and_filters(): void
    {
        $a = Product::factory()->create(['name' => 'Elbise A']);
        $b = Product::factory()->create(['name' => 'Pantolon B']);

        $this->makeResult($a, TryonResult::STATUS_DONE, TryonResult::REVIEW_PENDING);
        $this->makeResult($a, TryonResult::STATUS_DONE, TryonResult::REVIEW_APPROVED);
        $this->makeResult($a, TryonResult::STATUS_FAILED);
        $this->makeResult($b, TryonResult::STATUS_GENERATING);

        $rows = collect($this->actingAs($this->viewer())->getJson('/creative/tryon/product-summaries')
            ->assertOk()->json('data'))->keyBy('product_id');

        $this->assertSame(['total' => 3, 'pending' => 1, 'approved' => 1, 'running' => 0, 'failed' => 1],
            collect($rows[$a->id])->only(['total', 'pending', 'approved', 'running', 'failed'])->all());
        $this->assertNotNull($rows[$a->id]['thumb_url']);
        $this->assertSame(1, $rows[$b->id]['running']);
        $this->assertNull($rows[$b->id]['thumb_url']);

        $this->assertSame([$a->id], collect($this->getJson('/creative/tryon/product-summaries?filter=pending')->json('data'))->pluck('product_id')->all());
        $this->assertSame([$b->id], collect($this->getJson('/creative/tryon/product-summaries?filter=running')->json('data'))->pluck('product_id')->all());
        $this->assertSame([$b->id], collect($this->getJson('/creative/tryon/product-summaries?q=Pantolon')->json('data'))->pluck('product_id')->all());
    }

    public function test_requires_creative_view(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/creative/tryon/product-summaries')->assertForbidden();
    }
}
