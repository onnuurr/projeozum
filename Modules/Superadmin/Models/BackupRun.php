<?php

namespace Modules\Superadmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class BackupRun extends Model
{
    use Prunable;

    /** Bu kadar günden eski yedekleme kaydı log niteliğinde — kalıcı silinir. */
    public const PRUNE_AFTER_DAYS = 90;

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'status',
        'triggered_by',
        'started_at',
        'finished_at',
        'duration_seconds',
        'db_dump_bytes',
        'files_bytes',
        'remote_path',
        'error_message',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
        'duration_seconds' => 'integer',
        'db_dump_bytes'    => 'integer',
        'files_bytes'      => 'integer',
    ];

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
