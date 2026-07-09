<?php

namespace Modules\Product\Models;

use App\Support\Media;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_brand',
        'product_image',
        'color',
        'size',
        'qty',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'qty'         => 'integer',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /** Frontend, kapak görselini tam URL olarak `product_image_url`'den okur. */
    protected $appends = ['product_image_url'];

    /**
     * DB'de ham relative path (örn. "products/1/x.png") tutulur; tam URL aktif
     * medya diski üzerinden okuma anında üretilir (ProductImage ile aynı desen).
     */
    protected function productImageUrl(): Attribute
    {
        return Attribute::get(fn () => Media::url($this->product_image));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
