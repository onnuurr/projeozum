<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir giysi KAYNAK görseli için tek bir parça tespit taraması. image_hash ile
 * içerik bazlı dedup edilir — {@see \Modules\Creative\Services\GarmentScanService::scan()}.
 * detections JSON'u {@see \Modules\Creative\Services\Enhancement\GarmentPartDetectorContract}
 * çıktısıyla aynı şekli taşır (+ source:auto|manual|zeroshot ayrımı — bkz. ROADMAP.md
 * Faz G.3b: 'zeroshot' bir insan onaylayana kadar 'manual'a dönüşmez).
 */
class GarmentScan extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $table = 'creative_garment_scans';

    protected $fillable = [
        'image_hash', 'source_path', 'driver', 'model_version',
        'status', 'detections', 'identity_summary', 'error', 'duration_ms',
    ];

    protected $casts = [
        'detections'       => 'array',
        'identity_summary' => 'array',
        'duration_ms'      => 'integer',
    ];

    public function tryonResults(): HasMany
    {
        return $this->hasMany(TryonResult::class, 'garment_scan_id');
    }
}
