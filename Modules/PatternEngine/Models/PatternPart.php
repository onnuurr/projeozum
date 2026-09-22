<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatternPart extends Model
{
    protected $table = 'pattern_parts';

    protected $fillable = [
        'pattern_id', 'part_name', 'quantity', 'size_range',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }
}
