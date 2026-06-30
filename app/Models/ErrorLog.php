<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class ErrorLog extends Model
{
    use HasFactory;
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği varsayılan gün eşiği. */
    public const PRUNE_AFTER_DAYS = 90;

    protected $table = 'error_logs';

    protected $fillable = [
        'module',
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'trace',
        'url',
        'method',
        'causer_id',
        'ip_address',
        'context',
        'fingerprint',
        'occurred_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     * Eşik: security.logRetentionDays ayarından okunur; yoksa PRUNE_AFTER_DAYS sabiti.
     */
    public function prunable(): Builder
    {
        $days = self::PRUNE_AFTER_DAYS;

        try {
            $days = (int) (\Modules\Superadmin\Models\Setting::getGroup('security')['logRetentionDays'] ?? self::PRUNE_AFTER_DAYS);
        } catch (Throwable) {
            // Ayarlar tablosu henüz yok (erken migrate/test) — sabit değere dön.
        }

        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays($days));
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
