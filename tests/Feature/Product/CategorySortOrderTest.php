<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Models\Category;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kategori sırası: boş bırakılınca kardeşlerin (aynı üst kategori) sonuna eklenir, elle
 * verilen değer korunur; categories:renumber her grubu 1'den yeniden numaralandırır.
 */
class CategorySortOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Bagisto kategori senkron job'ları

        $this->user = User::factory()->create();
        foreach (['category.create', 'category.edit'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $this->user->givePermissionTo($p);
        }
    }

    private function store(string $slug, array $extra = []): Category
    {
        $this->actingAs($this->user)
            ->post(route('products.categories.store'), array_merge([
                'name' => ucfirst($slug), 'slug' => $slug, 'icon' => 'package', 'status' => 'active',
            ], $extra))
            ->assertSessionHasNoErrors();

        return Category::where('slug', $slug)->firstOrFail();
    }

    public function test_new_categories_get_next_order_within_their_parent(): void
    {
        $a = $this->store('kiz-cocuk');
        $b = $this->store('erkek-cocuk');
        $child1 = $this->store('dis-giyim', ['parent_id' => $a->id]);
        $child2 = $this->store('alt-giyim', ['parent_id' => $a->id]);
        $otherChild = $this->store('ceket', ['parent_id' => $b->id]);

        $this->assertSame([1, 2], [$a->sort_order, $b->sort_order]);
        $this->assertSame([1, 2], [$child1->sort_order, $child2->sort_order]);
        $this->assertSame(1, $otherChild->sort_order, 'her üst kategorinin kendi numaralandırması');
    }

    public function test_manual_order_is_kept(): void
    {
        $this->assertSame(7, $this->store('elbise', ['sort_order' => 7])->sort_order);
        $this->assertSame(8, $this->store('etek')->sort_order, 'otomatik sıra en büyüğün bir fazlası');
    }

    public function test_update_with_empty_order_keeps_it_or_moves_to_end_of_new_parent(): void
    {
        $a = $this->store('kiz-cocuk');
        $b = $this->store('erkek-cocuk');
        $this->store('ceket', ['parent_id' => $b->id]);
        $child = $this->store('dis-giyim', ['parent_id' => $a->id]);
        $payload = ['name' => 'Dış Giyim', 'slug' => 'dis-giyim', 'icon' => 'package', 'status' => 'active', 'sort_order' => null];

        $this->actingAs($this->user)->put(route('products.categories.update', $child), $payload + ['parent_id' => $a->id]);
        $this->assertSame(1, $child->fresh()->sort_order, 'aynı üstte sıra korunur');

        $this->actingAs($this->user)->put(route('products.categories.update', $child), $payload + ['parent_id' => $b->id]);
        $this->assertSame(2, $child->fresh()->sort_order, 'yeni üst kategoride sona eklenir');
    }

    public function test_renumber_command_orders_each_group_from_one(): void
    {
        $root = Category::create(['name' => 'Kız Çocuk', 'slug' => 'kiz', 'status' => 'active', 'sort_order' => 0]);
        foreach (['Elbise', 'Ceket', 'Alt Giyim'] as $name) {
            Category::create(['name' => $name, 'slug' => str($name)->slug(), 'parent_id' => $root->id, 'status' => 'active', 'sort_order' => 0]);
        }
        Category::create(['name' => 'Erkek Çocuk', 'slug' => 'erkek', 'status' => 'active', 'sort_order' => 5]);

        $this->artisan('categories:renumber')->assertSuccessful();

        $this->assertSame(
            ['Alt Giyim' => 1, 'Ceket' => 2, 'Elbise' => 3],
            Category::where('parent_id', $root->id)->orderBy('sort_order')->pluck('sort_order', 'name')->all(),
        );
        $this->assertSame(['Kız Çocuk' => 1, 'Erkek Çocuk' => 2], Category::whereNull('parent_id')->orderBy('sort_order')->pluck('sort_order', 'name')->all());
    }
}
