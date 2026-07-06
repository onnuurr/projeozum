<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantProductCustomCopyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private Product $product;
    private TenantProductAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['tenant-access.manage', 'tenant.product.customize'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('tenant-access.manage', 'tenant.product.customize');

        $this->tenant = Tenant::factory()->create();
        $category = Category::create([
            'name' => 'Kategori', 'slug' => 'k-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Fabrika Adı',
            'sku' => 'X-' . uniqid(), 'gender' => 'Unisex', 'price' => 100,
            'public_name'        => 'Storefront Adı',
            'tenant_description' => 'B2B varsayılan',
        ]);
        $this->access = TenantProductAccess::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $this->product->id,
            'is_blocked' => false,
        ]);
    }

    public function test_update_custom_copy_writes_to_pivot(): void
    {
        $this->actingAs($this->admin)
            ->put("/tenants/{$this->tenant->id}/access/overrides/{$this->access->id}/custom-copy", [
                'custom_name'        => 'Bu Bayiye Özel Ad',
                'custom_description' => '**Bu bayiye özel** metin.',
            ])
            ->assertRedirect();

        $fresh = $this->access->fresh();
        $this->assertSame('Bu Bayiye Özel Ad', $fresh->custom_name);
        $this->assertSame('**Bu bayiye özel** metin.', $fresh->custom_description);
    }

    public function test_permission_required(): void
    {
        Permission::firstOrCreate(['name' => 'tenant-access.manage', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('tenant-access.manage');  // customize izni YOK

        $this->actingAs($viewer)
            ->put("/tenants/{$this->tenant->id}/access/overrides/{$this->access->id}/custom-copy", [
                'custom_name' => 'X',
            ])
            ->assertForbidden();
    }

    public function test_empty_strings_persist_as_null(): void
    {
        $this->access->update([
            'custom_name'        => 'önceki',
            'custom_description' => 'önceki desc',
        ]);

        $this->actingAs($this->admin)
            ->put("/tenants/{$this->tenant->id}/access/overrides/{$this->access->id}/custom-copy", [
                'custom_name'        => '',
                'custom_description' => '',
            ])
            ->assertRedirect();

        $fresh = $this->access->fresh();
        $this->assertNull($fresh->custom_name);
        $this->assertNull($fresh->custom_description);
    }
}
