<?php

namespace Modules\TrainingIngest\Models;

class Mannequin extends TrainingModel
{
    protected $fillable = ['external_id', 'label', 'attributes'];

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
