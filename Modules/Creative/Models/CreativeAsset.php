<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class CreativeAsset extends Model
{
    protected $table = 'creative_assets';

    protected $fillable = [
        'product_id',
        'template_id',
        'image_path',
        'status',
        'review_status',
        'error',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    public const REVIEW_PENDING  = 'pending';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CreativeTemplate::class, 'template_id');
    }
}
