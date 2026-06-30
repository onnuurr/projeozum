<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSyncLog extends Model
{
    use Prunable;

    public const OPERATION_PUSH_PRODUCT  = 'push_product';
    public const OPERATION_PULL_ORDERS   = 'pull_orders';
    public const OPERATION_PULL_REPORTS  = 'pull_reports';
    public const OPERATION_WEBHOOK       = 'webhook';

    public const STATUS_QUEUED  = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $table = 'marketplace_sync_logs';

    protected $fillable = [
        'tenant_id',
        'marketplace',
        'operation',
        'status',
        'items_processed',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'items_processed' => 'integer',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 30 günden eski sync log'ları prune'lar. routes/console.php Schedule::command('model:prune')
     * ile günlük çalıştırılmalı.
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(30));
    }
}
