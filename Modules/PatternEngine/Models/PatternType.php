<?php

namespace Modules\PatternEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kalıp tipi (sweatshirt, pantolon…) — kendi ölçü şablonunu taşır. */
class PatternType extends Model
{
    protected $table = 'pattern_types';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /** Şablon: bu tipte doldurulacak ölçü noktaları (sıralı). */
    public function measurementPoints(): BelongsToMany
    {
        return $this->belongsToMany(PatternMeasurementPoint::class, 'pattern_type_measurement_points', 'pattern_type_id', 'measurement_point_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(Pattern::class);
    }
}
