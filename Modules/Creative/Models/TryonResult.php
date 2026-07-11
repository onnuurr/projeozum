<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * Bir ürünün, bir manken pozuna idm-vton ile giydirilme sonucu.
 *
 * Nihai görsel Product modülünün product_images tablosuna, ANCAK insan onayından
 * SONRA yazılır (bkz. ProductOnModelService::publish). Üretim biter bitmez çıktı
 * staged_image_path'e yazılır; bu satır (product_id + pose_id benzersiz) kaynak
 * takibi ve idempotensi sağlar.
 */
class TryonResult extends Model
{
    protected $table = 'creative_tryon_results';

    protected $fillable = [
        'product_id',
        'mannequin_id',
        'pose_id',
        'product_image_id',
        'staged_image_path',
        'status',
        'error',
        'meta',
        'created_by',
        'review_status',
        'review_note',
        'review_tags',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'review_tags' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    public const REVIEW_PENDING  = 'pending';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewChats(): MorphMany
    {
        return $this->morphMany(ReviewChat::class, 'subject');
    }
}
