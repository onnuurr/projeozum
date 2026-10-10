<?php

namespace Modules\PatternEngine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pattern extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    // PDF'ten otomatik çıkarım durumu (null = elle eklendi).
    public const EXTRACTION_PROCESSING = 'processing';
    public const EXTRACTION_DONE       = 'done';
    public const EXTRACTION_FAILED     = 'failed';
    // Raster/taranmış PDF → otomatik çıkarım yapılamaz; insan-destekli izleme bekler.
    public const EXTRACTION_NEEDS_TRACING = 'needs_tracing';

    protected $table = 'patterns';

    protected $fillable = [
        'code', 'name', 'product_type', 'pattern_type_id', 'size_range', 'sizes', 'variants',
        'vendor', 'collection',
        'scale_verified', 'scale_deviation_mm', 'dxf_path', 'pdf_path', 'photo_path',
        'preview_image_path', 'status', 'extraction_status', 'extraction_error',
        'size_layers', 'color_size_map', 'fabric_usage', 'materials',
        'seam_allowance_cm', 'hem_allowance_cm',
        'ai_metadata', 'notes', 'created_by',
    ];

    protected $casts = [
        'scale_verified'     => 'boolean',
        'scale_deviation_mm' => 'decimal:2',
        'sizes'              => 'array',
        'variants'           => 'array',
        'size_layers'        => 'array',
        'color_size_map'     => 'array',
        'fabric_usage'       => 'array',
        'materials'          => 'array',
        'seam_allowance_cm'  => 'decimal:2',
        'hem_allowance_cm'   => 'decimal:2',
        'ai_metadata'        => 'array',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(PatternPart::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PatternType::class, 'pattern_type_id');
    }

    /** Beden × ölçü noktası değerleri (vücut + hazır giysi). */
    public function measurements(): HasMany
    {
        return $this->hasMany(PatternMeasurement::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(PatternTag::class);
    }

    public function designCards(): HasMany
    {
        return $this->hasMany(DesignCard::class);
    }

    public function conversionJobs(): HasMany
    {
        return $this->hasMany(ConversionJob::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
