<?php

namespace Modules\TrainingIngest\Models;

class PromptVersion extends TrainingModel
{
    protected $fillable = [
        'version_label', 'template_text', 'template_sha256', 'changelog',
        'is_active', 'activated_at', 'deactivated_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'activated_at'   => 'immutable_datetime',
        'deactivated_at' => 'immutable_datetime',
        'created_at'     => 'immutable_datetime',
    ];
}
