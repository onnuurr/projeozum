<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mokap giydirme — kilitli baz görsel (kombinli manken, tek poz). Ana kare
 * (derived_from_base_id null) önce üretilir, diğer pozlar ondan türetilir.
 *
 * masks: {labels, region, occluders, shoes, crop:[x0,y0,x1,y1], long:bool} — yollar
 * config('creative.mockup.work_disk') üzerinde relative.
 */
class MockupBase extends Model
{
    protected $table = 'creative_mockup_bases';

    protected $fillable = [
        'outfit_id', 'pose_id', 'derived_from_base_id', 'image_path', 'masks', 'qa', 'status', 'attempts', 'error',
    ];

    protected $casts = [
        'masks' => 'array',
        'qa'    => 'array',
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public function outfit(): BelongsTo
    {
        return $this->belongsTo(MockupOutfit::class, 'outfit_id');
    }

    public function pose(): BelongsTo
    {
        return $this->belongsTo(Pose::class, 'pose_id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_base_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TryonResult::class, 'mockup_base_id');
    }
}
