<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Creative\Services\Enhancement\GarmentDetailClassifierContract;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * /creative/tryon/classify-detail: detay görseli için etiket ÖNERİSİ döner.
 * Gerçek sınıflandırıcı (Python/CLIP) yerine contract'ı sabit bir stub'a
 * bağlıyoruz — burada test edilen şey model doğruluğu değil, uç noktanın
 * sözleşmesi (yetki, validasyon, öneri şeklinde JSON dönüşü).
 */
class TryonClassifyDetailTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('creative.asset.manage');

        return $user;
    }

    public function test_returns_suggested_labels_from_bound_classifier(): void
    {
        $this->app->bind(GarmentDetailClassifierContract::class, function () {
            return new class implements GarmentDetailClassifierContract {
                public function classify(array $imagePaths): array
                {
                    return array_fill_keys($imagePaths, [
                        ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
                    ]);
                }
            };
        });

        $response = $this->actingAs($this->actingUser())
            ->post('/creative/tryon/classify-detail', [
                'image' => UploadedFile::fake()->image('detail.jpg'),
            ]);

        $response->assertOk();
        $response->assertJson([
            'labels' => [
                ['key' => 'yaka', 'display' => 'Yaka', 'score' => 0.82],
            ],
        ]);
    }

    public function test_requires_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)
            ->post('/creative/tryon/classify-detail', [
                'image' => UploadedFile::fake()->image('detail.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_validates_image_is_required(): void
    {
        // Bu uç nokta axios ile çağrılsa da testte "Accept: application/json"
        // gönderilmediğinden Laravel varsayılan web validasyon davranışına
        // (redirect + session errors) düşer — gerçek axios isteği XHR başlığı
        // gönderdiği için 422 JSON alır, burada asıl doğrulanan validasyon
        // kuralının çalıştığıdır.
        $this->actingAs($this->actingUser())
            ->post('/creative/tryon/classify-detail', [])
            ->assertSessionHasErrors('image');
    }
}
