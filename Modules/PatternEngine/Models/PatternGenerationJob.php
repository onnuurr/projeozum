<?php

namespace Modules\PatternEngine\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir kalıp üretim talebi (garment × size × profile) ve sonucu.
 *
 * Log niteliğinde büyüyen bir tablo (CLAUDE.md §5) — SoftDeletes + Prunable,
 * 30 gün eşik (bkz. StockMovement, aynı desen). PatternEngine hiçbir modüle
 * bağımlı olmadığı için bu satırın çağıran tarafla ilişkisi yalnızca
 * `external_reference` serbest metin alanıdır, FK değil.
 */
class PatternGenerationJob extends Model
{
    use Prunable;
    use SoftDeletes;

    public const PRUNE_AFTER_DAYS = 30;

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    protected $table = 'pattern_engine_jobs';

    protected $fillable = [
        'external_reference',
        'garment',
        'size',
        'graded_sizes',
        'profile',
        'design_params',
        'status',
        'generation_token',
        'result_dxf_path',
        'validation_report',
        'error',
    ];

    protected $casts = [
        'design_params'     => 'array',
        'graded_sizes'      => 'array',
        'validation_report' => 'array',
    ];

    public function isGraded(): bool
    {
        return ! empty($this->graded_sizes);
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
