<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gemini Batch API'ye giden tek bir görsel çağrısı (bkz. AiBatchService). İstek ve yanıt gövdeleri
 * iş diskinde dosyadır; satır silinirken (prune) dosyalar da silinir.
 * Log niteliğinde büyür → Prunable: biten satırlar PRUNE_AFTER_DAYS gün sonra silinir.
 */
class AiBatchRequest extends Model
{
    use Prunable;

    public const PRUNE_AFTER_DAYS = 14;

    public const STATUS_QUEUED    = 'queued';     // gönderilmeyi bekliyor (zamanlayıcı toplu gönderir)
    public const STATUS_SUBMITTED = 'submitted';  // Google'da işleniyor
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';

    protected $table = 'creative_ai_batch_requests';

    protected $fillable = [
        'key', 'provider', 'model', 'operation', 'status', 'batch_name', 'payload_path', 'response_path',
        'error', 'subject_type', 'subject_id', 'resume', 'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'resume'       => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDone(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED], true);
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('creative.mockup.work_disk', 'local'));
    }

    public function prunable(): Builder
    {
        return static::where('updated_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    protected function pruning(): void
    {
        static::disk()->delete(array_filter([$this->payload_path, $this->response_path]));
    }
}
