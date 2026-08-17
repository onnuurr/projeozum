<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Models\Product;
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

    private function jobProperty(object $job, string $name): mixed
    {
        $property = new \ReflectionProperty($job, $name);

        return $property->getValue($job);
    }
}
