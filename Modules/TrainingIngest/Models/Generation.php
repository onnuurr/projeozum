<?php

namespace Modules\TrainingIngest\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends TrainingModel
{
    protected $fillable = [
        'source_generation_id', 'mannequin_id', 'pose_id', 'product_id',
        'prompt_version_id', 'rendered_prompt', 'model_provider', 'model_name',
        'model_params', 'latency_ms', 'cost_usd', 'requested_at',
    ];

    protected $casts = [
        'model_params' => 'array',
        'latency_ms'   => 'integer',
        'cost_usd'     => 'decimal:6',
        'requested_at' => 'immutable_datetime',
        'created_at'   => 'immutable_datetime',
    ];

    public function mannequin(): BelongsTo
    {
        return $this->belongsTo(Mannequin::class);
    }

    public function pose(): BelongsTo
    {
        return $this->belongsTo(Pose::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(GenerationInput::class)->orderBy('position');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(GenerationOutput::class)->orderBy('variant_index');
    }
}
