<?php

namespace Modules\PatternEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kalıbın bir bedendeki tek ölçü değeri (vücut veya hazır giysi). */
class PatternMeasurement extends Model
{
    public const SOURCE_PDF    = 'pdf';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'pattern_measurements';

    protected $fillable = [
        'pattern_id', 'measurement_point_id', 'custom_label', 'basis',
        'group_label', 'size_label', 'value', 'source',
    ];

    protected $casts = [
        'value' => 'float',
    ];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(PatternMeasurementPoint::class, 'measurement_point_id');
    }
}
