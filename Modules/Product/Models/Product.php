<?php

namespace Modules\Product\Models;

use App\Models\User;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'gender',
        'price',
        'old_price',
        'market_price',
        'purchase_price',
        'rating',
        'review_count',
        'is_new',
        'free_shipping',
        'care_instructions',
        'material',
        'origin_country',
        // Açıklamalar (M2)
        'public_name',
        'public_description',
        'tenant_description',
        'ai_generated_at',
        // SEO
        'meta_title',
        'meta_description',
        'meta_keywords',
        // Kargo
        'weight',
        'desi',
        'shipping_time',
        'shipping_fee',
        // Diğer
        'barcode',
        'is_domestic',
        'manufacturer_code',
        'gtip_code',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'old_price'        => 'decimal:2',
        'market_price'     => 'decimal:2',
        'purchase_price'   => 'decimal:2',
        'rating'           => 'decimal:2',
        'review_count'     => 'integer',
        'is_new'           => 'boolean',
        'free_shipping'    => 'boolean',
        'weight'           => 'decimal:3',
        'desi'             => 'decimal:2',
        'shipping_fee'     => 'decimal:2',
        'is_domestic'      => 'boolean',
        'ai_generated_at'  => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ProductMarketplaceListing::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ProductFavorite::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites')
            ->withTimestamps();
    }

    public function descriptionMaterials(): HasMany
    {
        return $this->hasMany(ProductDescriptionMaterial::class)
            ->orderBy('sort_order');
    }

    public function boms(): HasMany
    {
        return $this->hasMany(\Modules\Atelier\Models\ProductBom::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Tenant'a görünür ürünleri döndürür (blacklist mode).
     *
     * Resolve sırası:
     *   1) tenant_product_access.is_blocked = true → ürün gizli
     *   2) tenant_product_access.is_blocked = false → ürün açık (kural override edilir)
     *   3) Pivot kaydı yoksa: scope kuralı (marka/kategori block) varsa gizli, yoksa açık
     */
    public function scopeAccessibleToTenant(Builder $query, ?int $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }

        return $query
            // is_blocked override = true ise hariç tut
            ->whereNotExists(function ($q) use ($tenantId) {
                $q->select(\DB::raw(1))
                    ->from('tenant_product_access as tpa_block')
                    ->whereColumn('tpa_block.product_id', 'products.id')
                    ->where('tpa_block.tenant_id', $tenantId)
                    ->where('tpa_block.is_blocked', true);
            })
            // Pivot whitelist (override is_blocked=false) varsa hariç tutma yok; kural varsa hariç tut.
            ->where(function ($q) use ($tenantId) {
                // Whitelist override
                $q->whereExists(function ($sub) use ($tenantId) {
                    $sub->select(\DB::raw(1))
                        ->from('tenant_product_access as tpa_allow')
                        ->whereColumn('tpa_allow.product_id', 'products.id')
                        ->where('tpa_allow.tenant_id', $tenantId)
                        ->where('tpa_allow.is_blocked', false);
                })
                // Veya: hiçbir blocking kural yok (blacklist default = açık)
                ->orWhereNotExists(function ($sub) use ($tenantId) {
                    $sub->select(\DB::raw(1))
                        ->from('tenant_access_rules as tar')
                        ->where('tar.tenant_id', $tenantId)
                        ->where('tar.is_blocked', true)
                        ->where(function ($c) {
                            $c->where(function ($x) {
                                $x->where('tar.scope_type', 'brand')
                                    ->whereColumn('tar.scope_id', 'products.brand_id');
                            })->orWhere(function ($x) {
                                $x->where('tar.scope_type', 'category')
                                    ->whereColumn('tar.scope_id', 'products.category_id');
                            });
                        });
                });
            });
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            // Slug elle girildiyse (değişmişse) onu koru; sadece normalize + benzersizleştir.
            if ($product->isDirty('slug') && ! empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->slug, $product->id);

                return;
            }

            // Aksi halde: slug boşsa ya da ad değiştiyse addan otomatik üret.
            $needsSlug = empty($product->slug) || $product->isDirty('name');
            if ($needsSlug) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }
        });
    }

    public static function generateUniqueSlug(?string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name ?: 'urun', '-', 'tr');
        if ($base === '') {
            $base = 'urun';
        }
        $slug = $base;
        $i    = 2;
        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
