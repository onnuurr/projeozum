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

    private function jobProperty(object $job, string $name): mixed
    {
        $property = new \ReflectionProperty($job, $name);

        return $property->getValue($job);
    }
}
