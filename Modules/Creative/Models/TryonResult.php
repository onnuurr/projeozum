<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * Bir ürünün, bir manken pozuna fashn/tryon ile giydirilme sonucu.
 *
 * Nihai görsel Product modülünün product_images tablosuna, ANCAK insan onayından
 * SONRA yazılır (bkz. ProductOnModelService::publish). Üretim biter bitmez çıktı
 * staged_image_path'e yazılır; bu satır (product_id + pose_id + shot benzersiz) kaynak
 * takibi ve idempotensi sağlar.
 *
 * shot: 'full' = tam boy giydirme; diğer değerler onaylı bir tam boy sonuçtan
 * (source_result_id) üretilen yakın plan / detay kadrajlarıdır (bkz. ProductCloseupService,
 * config('creative.closeup.shots')). Yakın planlar aynı onay/yayın akışından geçer.
 */
class TryonResult extends Model
{
    protected $table = 'creative_tryon_results';

    protected $fillable = [
        'product_id',
        'mannequin_id',
        'pose_id',
        'shot',
        // Mokap: giydirilen ürün rengi (product_variants.color_name); '' = renksiz / genel set.
        'color',
        'pipeline',
        'mockup_base_id',
        'source_result_id',
        'garment_image_path',
        'garment_scan_id',
        'product_image_id',
        'staged_image_path',
        'status',
        'generation_token',
        'tryon_driver',
        'tryon_model',
        'generation_duration_ms',
        'error',
        'meta',
        'qa',
        'created_by',
        'review_status',
        'review_note',
        'review_tags',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'qa'          => 'array',
        'review_tags' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    public const SHOT_FULL = 'full';

    // legacy: manken her üretimde pozlanır (ProductOnModelService) · mockup: kilitli baz + maske +
    // birleştirme (MockupTryOnService). Tekillik (product_id, pose_id, shot, pipeline).
    public const PIPELINE_LEGACY = 'legacy';
    public const PIPELINE_MOCKUP = 'mockup';

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

    /** Yakın planın üretildiği tam boy giydirme. */
    public function sourceResult(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_result_id');
    }

    public function mockupBase(): BelongsTo
    {
        return $this->belongsTo(MockupBase::class, 'mockup_base_id');
    }

    public function isMockup(): bool
    {
        return ($this->pipeline ?? self::PIPELINE_LEGACY) === self::PIPELINE_MOCKUP;
    }

    public function isCloseup(): bool
    {
        return ($this->shot ?? self::SHOT_FULL) !== self::SHOT_FULL;
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function garmentScan(): BelongsTo
    {
        return $this->belongsTo(GarmentScan::class, 'garment_scan_id');
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
