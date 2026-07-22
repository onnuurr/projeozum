<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Product\Models\Product;

class CreativeAsset extends Model
{
    protected $table = 'creative_assets';

    protected $fillable = [
        'product_id',
        'template_id',
        'created_by',
        'image_path',
        'status',
        'review_status',
        'review_note',
        'review_tags',
        'error',
        'meta',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'review_tags' => 'array',
        'reviewed_at' => 'datetime',
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
