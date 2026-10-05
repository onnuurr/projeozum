<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tek bir başarılı AI çağrısının maliyet kaydı (bkz. AiUsageRecorder). est_cost_usd tahmindir.
 * Log niteliğinde büyür → Prunable: PRUNE_AFTER_DAYS günden eskiler silinir (yıllık kıyas için 400).
 */
class AiUsage extends Model
{
    use Prunable;

    public const PRUNE_AFTER_DAYS = 400;

    protected $table = 'creative_ai_usages';

    protected $fillable = [
        'provider', 'model', 'operation', 'image_size', 'input_tokens', 'output_tokens', 'images_out',
        'est_cost_usd', 'subject_type', 'subject_id',
    ];

    protected $casts = [
        'est_cost_usd' => 'float',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
