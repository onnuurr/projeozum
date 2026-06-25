<?php

namespace Modules\Atelier\Models;

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
        'code', 'name', 'product_type', 'size_range', 'vendor', 'collection',
        'scale_verified', 'scale_deviation_mm', 'dxf_path', 'pdf_path',
        'preview_image_path', 'status', 'extraction_status', 'extraction_error',
        'size_layers', 'color_size_map', 'measurements', 'fabric_usage',
        'notes', 'created_by',
    ];

    protected $casts = [
        'scale_verified'     => 'boolean',
        'scale_deviation_mm' => 'decimal:2',
        'size_layers'        => 'array',
        'color_size_map'     => 'array',
        'measurements'       => 'array',
        'fabric_usage'       => 'array',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(PatternPart::class);
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
