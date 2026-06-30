<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mankenden BAĞIMSIZ poz kütüphanesi öğesi.
 *
 * Her pozun nötr bir figür üzerinde üretilmiş önizlemesi vardır (preview_image_path).
 * Giydirme aşamasında seçilen gerçek manken, bu pozun prompt'uyla poza sokulur.
 */
class Pose extends Model
{
    protected $table = 'creative_poses';

    protected $fillable = [
        'pose_key',
        'label',
        'prompt',
        'preview_image_path',
        'sort_order',
        'status',
        'error',
        'meta',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'meta'       => 'array',
    ];

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public function results(): HasMany
    {
        return $this->hasMany(TryonResult::class, 'pose_id');
    }
}
