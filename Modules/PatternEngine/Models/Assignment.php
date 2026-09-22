<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    public const KIND_PATTERN_MAKER = 'pattern_maker';
    public const KIND_DESIGNER       = 'designer';

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DELIVERED   = 'delivered';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_REJECTED    = 'rejected';

    protected $table = 'atelier_assignments';

    protected $fillable = [
        'design_card_id', 'pattern_id', 'kind', 'assigned_to', 'assigned_by',
        'title', 'instructions', 'due_date', 'status',
        'delivered_file_path', 'delivered_at', 'accepted_at', 'review_note',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'delivered_at' => 'datetime',
        'accepted_at'  => 'datetime',
    ];

    public function designCard(): BelongsTo
    {
        return $this->belongsTo(DesignCard::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Açık (henüz sonuçlanmamış) işler. */
    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true);
    }
}
