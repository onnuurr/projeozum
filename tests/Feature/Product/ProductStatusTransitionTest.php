<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductTimelineEntry;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 2: Product Lifecycle durum makinesi HTTP ucu.
 */
class ProductStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    public function test_requires_permission(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_PUBLISHED]);

        $this->actingAs($this->user())
            ->put(route('products.update-status', $product), ['status' => Product::STATUS_DRAFT])
            ->assertForbidden();
    }

    public function test_valid_transition_succeeds_and_records_history_and_timeline(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_PUBLISHED]);

        $this->actingAs($this->user(['product.add']))
            ->put(route('products.update-status', $product), ['status' => Product::STATUS_DRAFT])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(Product::STATUS_DRAFT, $product->status);
        $this->assertDatabaseHas('product_status_histories', [
            'product_id'  => $product->id,
            'from_status' => Product::STATUS_PUBLISHED,
            'to_status'   => Product::STATUS_DRAFT,
        ]);
        // draft için event yok -> timeline satırı beklenmiyor (ROADMAP madde 6).
        $this->assertSame(0, ProductTimelineEntry::where('product_id', $product->id)->count());
    }

    public function test_publish_transition_dispatches_timeline_event(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->actingAs($this->user(['product.add']))
            ->put(route('products.update-status', $product), ['status' => Product::STATUS_PUBLISHED])
            ->assertRedirect();

        $this->assertDatabaseHas('product_timeline_events', [
            'product_id' => $product->id,
            'type'       => 'product.published',
        ]);
    }

    public function test_invalid_status_value_returns_validation_error(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_PUBLISHED]);

        $this->actingAs($this->user(['product.add']))
            ->put(route('products.update-status', $product), ['status' => 'nonexistent'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Product::STATUS_PUBLISHED, $product->fresh()->status);
    }
}
