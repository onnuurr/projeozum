<?php

namespace Modules\TrainingIngest\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends TrainingModel
{
    public const VERDICT_APPROVED = 'approved';
    public const VERDICT_REJECTED = 'rejected';

    protected $fillable = [
        'generation_output_id', 'verdict', 'reason_code', 'reason_text',
        'severity', 'reviewer', 'reviewed_at', 'superseded_by',
    ];

    protected $casts = [
        'severity'     => 'integer',
        'reviewed_at'  => 'immutable_datetime',
        'created_at'   => 'immutable_datetime',
    ];

    public function generationOutput(): BelongsTo
    {
        return $this->belongsTo(GenerationOutput::class);
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class, 'reason_code', 'code');
    }

    public function supersededByReview(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }
}
