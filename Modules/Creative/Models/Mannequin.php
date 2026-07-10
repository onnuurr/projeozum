<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI ile üretilen yeniden kullanılabilir sanal manken.
 *
 * reference_image_path, giydirme aşamasında bağımsız {@see Pose} ile birlikte
 * kimlik referansı olarak kullanılır; giydirme sonuçları {@see TryonResult}.
 */
class Mannequin extends Model
{
    use SoftDeletes;

    protected $table = 'creative_mannequins';

    protected $fillable = [
        'name',
        'gender',
        'age_range',
        'skin_tone',
        'body_type',
        'hair',
        'face',
        'height_cm',
        'bust_cm',
        'waist_cm',
        'hips_cm',
        'extras',
        'prompt',
        'reference_image_path',
        'status',
        'error',
        'meta',
        'created_by',
        'review_status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'height_cm'   => 'integer',
        'bust_cm'     => 'integer',
        'waist_cm'    => 'integer',
        'hips_cm'     => 'integer',
        'meta'        => 'array',
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public const REVIEW_PENDING  = 'pending';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    public function results(): HasMany
    {
        return $this->hasMany(TryonResult::class, 'mannequin_id');
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
