<?php

namespace Modules\TrainingIngest\Models;

class RejectionReason extends TrainingModel
{
    protected $primaryKey = 'id';

    protected $fillable = ['code', 'label', 'description', 'category', 'context', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'immutable_datetime',
    ];

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reason_code', 'code');
    }
}
