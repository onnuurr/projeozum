<?php

namespace Modules\TrainingIngest\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationOutput extends TrainingModel
{
    protected $fillable = ['generation_id', 'asset_id', 'variant_index'];

    protected $casts = ['variant_index' => 'integer'];

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Bir output birden fazla review'e sahip olabilir — düzeltme zinciri (bkz.
     * training.reviews.superseded_by): eski karar silinmez, yenisi eklenir.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function currentReview(): HasMany
    {
        return $this->reviews()->whereNull('superseded_by');
    }
}
