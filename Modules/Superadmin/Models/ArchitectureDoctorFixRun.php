<?php

namespace Modules\Superadmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchitectureDoctorFixRun extends Model
{
    use Prunable;

    /** Bu kadar günden eski düzeltme kaydı log niteliğinde — kalıcı silinir. */
    public const PRUNE_AFTER_DAYS = 90;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'triggered_by_user_id',
        'branch_name',
        'base_commit_sha',
        'started_at',
        'finished_at',
        'duration_seconds',
        'rules_before',
        'rules_after',
        'files_changed',
        'log_output',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
        'rules_before' => 'array',
        'rules_after' => 'array',
        'files_changed' => 'array',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
