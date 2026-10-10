<?php

namespace Modules\PatternEngine\Models;

use Illuminate\Database\Eloquent\Model;

/** Ölçü noktası sözlüğü (Kalıp Ayarları › Ölçü Noktaları). */
class PatternMeasurementPoint extends Model
{
    public const BASIS_BODY    = 'body';     // vücut ölçüsü (PDF beden tabloları)
    public const BASIS_GARMENT = 'garment';  // hazır giysi ölçüsü (üretim kontrolü)
    public const BASES = [self::BASIS_BODY, self::BASIS_GARMENT];

    protected $table = 'pattern_measurement_points';

    protected $fillable = ['key', 'name', 'hint', 'basis', 'unit', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];
}
