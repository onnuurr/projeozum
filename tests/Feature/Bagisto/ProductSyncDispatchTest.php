<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Services\ProductService;
use Tests\TestCase;

/**
 * Faz 1: Product modülünün domain event'leri gerçekten fırlatıldığında
 * (mock'lanmadan), Bagisto\Providers\EventServiceProvider'daki bağlantının
 * doğru job'u doğru argümanlarla kuyruğa attığını doğrular.
 */
class ProductSyncDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_created_dispatches_push_job_with_created_event(): void
    {
        Queue::fake();

        $product = Product::factory()->create();

        event(new ProductCreated($product));

        Queue::assertPushed(PushProductToBagisto::class, function ($job) use ($product) {
            return $this->jobProperty($job, 'productId') === $product->id
                && $this->jobProperty($job, 'event') === 'created';
        });
    }

    public function test_product_updated_dispatches_push_job_with_updated_event(): void
    {
        Queue::fake();

        $product = Product::factory()->create();

        event(new ProductUpdated($product));

        Queue::assertPushed(PushProductToBagisto::class, function ($job) use ($product) {
            return $this->jobProperty($job, 'productId') === $product->id
                && $this->jobProperty($job, 'event') === 'updated';
        });
    }

    public function test_product_deleted_dispatches_push_job_with_deleted_event(): void
    {
        Queue::fake();

        $product = Product::factory()->create();

        event(new ProductDeleted($product));

        Queue::assertPushed(PushProductToBagisto::class, function ($job) use ($product) {
            return $this->jobProperty($job, 'productId') === $product->id
                && $this->jobProperty($job, 'event') === 'deleted';
        });
    }

    /**
     * Mağazadaki "status" alanı yalnız upsert payload'ında taşınır; eskiden yaşam döngüsü
     * geçişleri (ProductPublished/ProductArchived, published → draft hiç event'siz)
     * Bagisto'ya gitmiyordu — arşivlenen ürün mağazada satışta kalıyordu.
     */
    public function test_every_status_transition_dispatches_updated_push(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);
        $actor   = User::factory()->create();
        $service = app(ProductService::class);

        foreach ([Product::STATUS_PUBLISHED, Product::STATUS_DRAFT, Product::STATUS_ARCHIVED, Product::STATUS_PUBLISHED] as $to) {
            Queue::fake();

            $service->transitionStatus($product->fresh(), $to, $actor);

            Queue::assertPushed(PushProductToBagisto::class, function ($job) use ($product) {
                return $this->jobProperty($job, 'productId') === $product->id
                    && $this->jobProperty($job, 'event') === 'updated';
            });
        }
    }

    /**
     * Görsel değişiklikleri ProductService'i atlayan yollardan da gelir (ProductImageController,
     * Creative "Kapak yap"/try-on onayı) — model olayından duyurulur.
     */
    public function test_image_add_cover_change_and_delete_dispatch_updated_push(): void
    {
        $product = Product::factory()->create();

        $steps = [
            'add'    => fn () => ProductImage::create(['product_id' => $product->id, 'path' => 'p/a.png', 'sort_order' => 1]),
            'cover'  => fn () => ProductImage::where('product_id', $product->id)->first()->update(['is_cover' => true]),
            'delete' => fn () => ProductImage::where('product_id', $product->id)->first()->delete(),
        ];

        foreach ($steps as $step => $run) {
            Queue::fake();
            $run();
            Queue::assertPushed(PushProductToBagisto::class, fn ($job) => $this->jobProperty($job, 'productId') === $product->id
                && $this->jobProperty($job, 'event') === 'updated');
        }
    }

    public function test_prune_force_delete_does_not_dispatch(): void
    {
        $product = Product::factory()->create();
        $image   = ProductImage::create(['product_id' => $product->id, 'path' => 'p/a.png', 'sort_order' => 1]);
        $image->delete();

        Queue::fake();
        $image->forceDelete();

        Queue::assertNotPushed(PushProductToBagisto::class);
    }

    public function test_product_service_create_with_images_pushes_only_created(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Support\Media::disk());
        Queue::fake();

        $category = \Modules\Product\Models\Category::create(['name' => 'Elbise', 'slug' => 'elbise-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product  = app(ProductService::class)->create([
            'category_id' => $category->id,
            'name'        => 'Görselli Ürün',
            'sku'         => 'PRD-'.uniqid(),
            'gender'      => 'Unisex',
            'variants'    => [['sku' => 'V-'.uniqid(), 'price' => 100, 'stock' => 0]],
        ], [\Illuminate\Http\UploadedFile::fake()->image('a.jpg'), \Illuminate\Http\UploadedFile::fake()->image('b.jpg')]);

        $this->assertSame(2, $product->images()->count());
        Queue::assertPushed(PushProductToBagisto::class, fn ($job) => $this->jobProperty($job, 'event') === 'created');
        Queue::assertNotPushed(PushProductToBagisto::class, fn ($job) => $this->jobProperty($job, 'event') === 'updated');
    }

    private function jobProperty(object $job, string $name): mixed
    {
        $property = new \ReflectionProperty($job, $name);

        return $property->getValue($job);
    }
}
