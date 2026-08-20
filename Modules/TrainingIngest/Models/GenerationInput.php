<?php

namespace Modules\TrainingIngest\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationInput extends TrainingModel
{
    public const KIND_FRONT = 'front';
    public const KIND_BACK = 'back';
    public const KIND_SIDE = 'side';
    public const KIND_SEAM = 'seam';
    public const KIND_COLLAR = 'collar';
    public const KIND_DETAIL = 'detail';
    public const KIND_MANNEQUIN = 'mannequin';
    public const KIND_OTHER = 'other';

    protected $fillable = ['generation_id', 'asset_id', 'position', 'input_kind'];

    protected $casts = ['position' => 'integer'];

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
