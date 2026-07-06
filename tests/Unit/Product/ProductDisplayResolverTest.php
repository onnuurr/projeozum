<?php

namespace Tests\Unit\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\DTOs\ProductDisplayDto;
use Modules\Product\Services\ProductDisplayResolver;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;
use Tests\TestCase;

class ProductDisplayResolverTest extends TestCase
{
    use RefreshDatabase;

    private ProductDisplayResolver $resolver;
    private Product $product;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ProductDisplayResolver::class);

        $category = Category::create([
            'name' => 'Kategori', 'slug' => 'k-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Fabrika Adı',
            'sku' => 'X-' . uniqid(), 'gender' => 'Unisex', 'price' => 100,
            'public_name'        => 'Storefront Adı',
            'public_description' => 'B2C metni',
            'tenant_description' => 'B2B varsayılan metni',
            'care_instructions'  => 'Bakım',
        ]);

        $this->tenant = Tenant::factory()->create();
    }

    public function test_no_tenant_returns_public_name_and_tenant_description(): void
    {
        $out = $this->resolver->for($this->product, null);
        $this->assertSame('Storefront Adı', $out->name);
        $this->assertSame('B2B varsayılan metni', $out->description);
        $this->assertSame(ProductDisplayDto::SOURCE_DEFAULT, $out->source);
    }

    public function test_tenant_override_wins(): void
    {
        TenantProductAccess::create([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $this->product->id,
            'is_blocked'         => false,
            'custom_name'        => 'Bayiye Özel',
            'custom_description' => 'Bayiye özel metni',
        ]);

        $out = $this->resolver->for($this->product->fresh(), $this->tenant);
        $this->assertSame('Bayiye Özel', $out->name);
        $this->assertSame('Bayiye özel metni', $out->description);
        $this->assertSame(ProductDisplayDto::SOURCE_TENANT_OVERRIDE, $out->source);
    }

    public function test_falls_back_through_chain_when_fields_empty(): void
    {
        $this->product->update([
            'public_name'        => null,
            'public_description' => null,
            'tenant_description' => null,
        ]);

        $out = $this->resolver->for($this->product->fresh(), null);
        $this->assertSame('Fabrika Adı', $out->name);
        $this->assertSame('Bakım', $out->description);
    }

    public function test_partial_override_only_name(): void
    {
        TenantProductAccess::create([
            'tenant_id'   => $this->tenant->id,
            'product_id'  => $this->product->id,
            'is_blocked'  => false,
            'custom_name' => 'Bayi X Adı',
        ]);

        $out = $this->resolver->for($this->product->fresh(), $this->tenant);
        $this->assertSame('Bayi X Adı', $out->name);
        // description override yok → tenant_description'a düşer
        $this->assertSame('B2B varsayılan metni', $out->description);
        $this->assertSame(ProductDisplayDto::SOURCE_TENANT_OVERRIDE, $out->source);
    }
}
