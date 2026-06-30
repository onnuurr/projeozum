<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * Bir ürünün, bir manken pozuna idm-vton ile giydirilme sonucu.
 *
 * Nihai görsel Product modülünün product_images tablosuna yazılır;
 * bu satır (product_id + pose_id benzersiz) kaynak takibi ve idempotensi sağlar.
 */
class TryonResult extends Model
{
    protected $table = 'creative_tryon_results';

    protected $fillable = [
        'product_id',
        'mannequin_id',
        'pose_id',
        'product_image_id',
        'status',
        'error',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mannequin(): BelongsTo
    {
        return $this->belongsTo(Mannequin::class, 'mannequin_id');
    }

    public function pose(): BelongsTo
    {
        return $this->belongsTo(Pose::class, 'pose_id');
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }
}
