<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * TryonController::store() detay görsellerinin yerel sınıflandırıcıdan (bkz.
 * classify-detail uç noktası) gelen TÜM aday etiketleri ('detected_labels')
 * meta.garment_extras'a kaydetmeli — raporlama/detay sayfası (tryon.show) bu
 * veriyi okur. Kullanılan 'label' kullanıcının elle yazdığı/düzenlediği değerdir,
 * detected_labels yalnız bilgi amaçlıdır ve giydirme pipeline'ını etkilemez.
 */
class TryonStoreGarmentDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.asset.manage');

        return $user;
    }

    public function test_store_persists_detected_labels_alongside_manual_label(): void
    {
        Queue::fake();

        $product   = Product::factory()->create();
        $mannequin = Mannequin::create([
            'name' => 'Test Manken', 'status' => Mannequin::STATUS_READY,
            'reference_image_path' => 'mannequins/1/reference.png',
            'review_status' => Mannequin::REVIEW_APPROVED,
        ]);
        $pose = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        $this->actingAs($this->actingUser())->post('/creative/tryon', [
            'product_id'    => $product->id,
            'mannequin_id'  => $mannequin->id,
            'pose_ids'      => [$pose->id],
            'garment_image' => UploadedFile::fake()->image('garment.jpg'),
            'garment_details' => [
                [
                    'image' => UploadedFile::fake()->image('detail.jpg'),
                    'label' => 'Yaka',
                    'detected_labels' => [
                        ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
                        ['key' => 'dugme', 'display' => 'Düğme', 'score' => 0.11],
                    ],
                ],
            ],
        ])->assertRedirect();

        $result = TryonResult::query()->where('product_id', $product->id)->where('pose_id', $pose->id)->firstOrFail();

        $extras = $result->meta['garment_extras'] ?? [];
        $this->assertCount(1, $extras);
        $this->assertSame('Yaka', $extras[0]['label']);
        $this->assertSame([
            ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
            ['key' => 'dugme', 'display' => 'Düğme', 'score' => 0.11],
        ], $extras[0]['detected_labels']);

        Queue::assertPushed(GenerateOnModelJob::class);
    }

    public function test_show_page_renders_garment_extras_with_detected_labels(): void
    {
        $product = Product::factory()->create();
        $pose    = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);
        $result  = TryonResult::create([
            'product_id' => $product->id, 'pose_id' => $pose->id,
            'status' => TryonResult::STATUS_DONE, 'tryon_driver' => 'gemini',
            'tryon_model' => 'gemini-3.1-flash-image', 'generation_duration_ms' => 4200,
            'meta' => [
                'garment_extras' => [
                    ['path' => 'creative/tryon_garments/detail.jpg', 'label' => 'Yaka', 'detected_labels' => [
                        ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
                    ]],
                ],
            ],
        ]);

        $response = $this->actingAs($this->actingUser())->get("/creative/tryon/{$result->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Creative::CreativeTryonDetail')
            ->where('result.tryon_model', 'gemini-3.1-flash-image')
            ->where('result.generation_duration_ms', 4200)
            ->where('garmentExtras.0.label', 'Yaka')
            ->where('garmentExtras.0.detected_labels.0.display', 'Yaka')
        );
    }
}
