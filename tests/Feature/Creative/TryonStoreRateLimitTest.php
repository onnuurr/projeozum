<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * POST /creative/tryon her çağrıda ücretli bir AI giydirme işini kuyruğa atar
 * (GenerateOnModelJob) ve önceden hiç hız sınırı yoktu. Modules/Creative/routes/web.php'ye
 * throttle:15,1 eklendi; bu test korumayı doğrular.
 */
class TryonStoreRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_tryon_store_is_rate_limited_after_15_requests_per_minute(): void
    {
        Queue::fake();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.asset.manage');

        $product = Product::factory()->create();
        $mannequin = Mannequin::create([
            'name' => 'Test Manken', 'status' => Mannequin::STATUS_READY,
            'reference_image_path' => 'mannequins/1/reference.png',
            'review_status' => Mannequin::REVIEW_APPROVED,
        ]);
        $pose = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        $this->actingAs($user);

        $payload = fn () => [
            'product_id'    => $product->id,
            'mannequin_id'  => $mannequin->id,
            'pose_ids'      => [$pose->id],
            'garment_image' => UploadedFile::fake()->image('garment.jpg'),
        ];

        for ($i = 0; $i < 15; $i++) {
            $this->post('/creative/tryon', $payload())->assertRedirect();
        }

        $this->post('/creative/tryon', $payload())->assertStatus(429);
    }
}
