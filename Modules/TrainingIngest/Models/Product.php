<?php

namespace Modules\TrainingIngest\Models;

class Product extends TrainingModel
{
    protected $fillable = ['external_id', 'sku', 'name', 'category', 'attributes'];

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
