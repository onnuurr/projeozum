<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $casts = [
        'height_cm' => 'integer',
        'bust_cm'   => 'integer',
        'waist_cm'  => 'integer',
        'hips_cm'   => 'integer',
        'meta'      => 'array',
    ];

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public function results(): HasMany
    {
        return $this->hasMany(TryonResult::class, 'mannequin_id');
    }
}
