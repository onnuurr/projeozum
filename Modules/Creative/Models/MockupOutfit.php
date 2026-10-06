<?php

namespace Modules\Creative\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

/**
 * Mokap giydirme — bir ürün × manken için stilistin kurduğu kombin. Ürünün bütün
 * pozları bu kombinin baz görselleri üzerinde giydirilir (bkz. MockupPipelineService).
 */
class MockupOutfit extends Model
{
    protected $table = 'creative_mockup_outfits';

    protected $fillable = [
        'product_id', 'mannequin_id', 'region', 'length', 'length_source', 'pieces', 'outfit_sentence',
        'poses', 'reasoning_tr', 'stylist_prompt_version', 'status', 'error', 'meta', 'created_by',
    ];

    protected $casts = [
        'pieces' => 'array',
        'poses'  => 'array',
        'meta'   => 'array',
    ];

    public const REGION_BOTTOM    = 'bottom';
    public const REGION_TOP       = 'top';
    public const REGION_ONE_PIECE = 'one_piece';
    public const REGION_SET       = 'set';
    // Faz 6: başka bir kıyafetin ÜSTÜNE giyilen dış katman (ceket, mont, yelek, yağmurluk).
    public const REGION_OUTERWEAR = 'outerwear';
    public const REGIONS = [self::REGION_BOTTOM, self::REGION_TOP, self::REGION_ONE_PIECE, self::REGION_SET, self::REGION_OUTERWEAR];

    public const LENGTHS = ['mini', 'knee', 'midi', 'maxi'];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public function isLong(): bool
    {
        return in_array($this->length, ['midi', 'maxi'], true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mannequin(): BelongsTo
    {
        return $this->belongsTo(Mannequin::class, 'mannequin_id');
    }

    /**
     * Bu kombine ait tüm AI çağrılarının tahmini maliyeti: kombin (stilist) + baz görseller +
     * bu bazlardan üretilen giydirme sonuçları.
     *
     * @return array{calls:int, images:int, usd:float}
     */
    public function aiCost(): array
    {
        $baseIds = $this->bases()->pluck('id');
        $resultIds = TryonResult::whereIn('mockup_base_id', $baseIds)->pluck('id');
        $row = AiUsage::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('subject_type', $this->getMorphClass())->where('subject_id', $this->id))
                ->orWhere(fn ($q) => $q->where('subject_type', (new MockupBase)->getMorphClass())->whereIn('subject_id', $baseIds))
                ->orWhere(fn ($q) => $q->where('subject_type', (new TryonResult)->getMorphClass())->whereIn('subject_id', $resultIds)))
            ->selectRaw('count(*) as calls, coalesce(sum(images_out),0) as images, coalesce(sum(est_cost_usd),0) as usd')
            ->first();

        return ['calls' => (int) $row->calls, 'images' => (int) $row->images, 'usd' => round((float) $row->usd, 4)];
    }

    public function bases(): HasMany
    {
        return $this->hasMany(MockupBase::class, 'outfit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
