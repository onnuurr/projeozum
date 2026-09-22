<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversionJob extends Model
{
    use SoftDeletes;

    // Sınıflandırma kovaları.
    public const CLASS_GREEN  = 'green';
    public const CLASS_YELLOW = 'yellow';
    public const CLASS_RED    = 'red';

    public const STATUS_PENDING      = 'pending';
    public const STATUS_PROCESSING   = 'processing';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_APPROVED     = 'approved';
    public const STATUS_REJECTED     = 'rejected';
    public const STATUS_FAILED       = 'failed';

    protected $table = 'conversion_jobs';

    protected $fillable = [
        'source_pdf_path', 'output_dxf_path', 'classification', 'confidence_score',
        'status', 'error_report', 'pattern_id', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'error_report'     => 'array',
        'reviewed_at'      => 'datetime',
    ];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
