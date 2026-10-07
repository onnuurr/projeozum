<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Toplu stok işlemi başlığı (bkz. StockService::bulk()). Her değişen varyant için bir StockMovement
 * yazılır ve reference olarak bu kayda bağlanır — işlemin satır satır logu oradadır.
 * Prunable değil: düşük hacimli, denetim için kalıcı tutulan iş kaydı.
 */
class StockOperation extends Model
{
    public const MODE_IN    = 'in';      // girilen miktar kadar giriş
    public const MODE_OUT   = 'out';     // girilen miktar kadar çıkış
    public const MODE_COUNT = 'count';   // sayım: stok girilen miktara eşitlenir (fark düzeltme hareketi)

    public const MODES = [self::MODE_IN, self::MODE_OUT, self::MODE_COUNT];

    protected $fillable = [
        'warehouse_id', 'mode', 'note', 'user_id', 'line_count', 'skipped_count', 'total_in', 'total_out',
    ];

    protected $casts = [
        'line_count'    => 'integer',
        'skipped_count' => 'integer',
        'total_in'      => 'integer',
        'total_out'     => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
