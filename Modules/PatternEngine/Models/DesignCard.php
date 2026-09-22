<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignCard extends Model
{
    use SoftDeletes;

    // Akış X = önce kalıp seç; Akış Y = önce AI konsept üret.
    public const SOURCE_PATTERN_FIRST = 'pattern_first';
    public const SOURCE_CONCEPT_FIRST = 'concept_first';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_MATCHED   = 'matched';
    public const STATUS_ARCHIVED  = 'archived';

    // Asenkron görsel üretimi durumu (null = eski/senkron kayıt).
    public const GENERATION_PROCESSING = 'processing';
    public const GENERATION_DONE       = 'done';
    public const GENERATION_FAILED     = 'failed';

    protected $table = 'design_cards';

    protected $fillable = [
        'source', 'prompt', 'product_type', 'target_size',
        'generated_images', 'pattern_id', 'status', 'created_by',
        'generation_status', 'generation_error', 'request_params',
    ];

    protected $casts = [
        'generated_images' => 'array',
        'request_params'   => 'array',
    ];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
