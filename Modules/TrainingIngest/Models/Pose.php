<?php

namespace Modules\TrainingIngest\Models;

class Pose extends TrainingModel
{
    protected $fillable = ['external_id', 'label', 'description', 'attributes'];

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
