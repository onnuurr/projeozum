<?php

namespace Modules\Product\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Events\ProductImagesChanged;

class ProductImage extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 30;

    protected $table = 'product_images';

    /** false iken değişiklikler ProductImagesChanged ile duyurulmaz (bkz. withoutChangeAnnouncements). */
    private static bool $announceChanges = true;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        // Bedene özel görsel: ait olduğu beden adları; null = genel (bkz. migration 2026_10_06_180000).
        'variant_sizes',
        'path',
        'alt_text',
        'sort_order',
        'is_cover',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_cover'   => 'boolean',
        'variant_sizes' => 'array',
    ];

    /** variant_sizes kolonu var mı (migration çalışmadan yazan kod canlıyı kırmasın). */
    public static function supportsVariantSizes(): bool
    {
        static $has = null;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn((new static)->getTable(), 'variant_sizes');
    }

    /** Frontend, görseli `url` anahtarından okur; relative path'ten türetilir. */
    protected $appends = ['url'];

    /**
     * DB'de relative path (örn. "products/1/x.png") tutulur; tam URL aktif
     * medya diski (local /storage veya R2/CDN) üzerinden okuma anında üretilir.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Media::url($this->path));
    }

    /**
     * Görsel kümesindeki her değişikliği ProductImagesChanged ile duyurur (Bagisto mağaza
     * görselleri bununla güncel kalır). Toplu `query()->update()` model olayı üretmez; mevcut
     * yazma yolları bunu hep ardından tekil bir model create/update ile tamamlıyor.
     * Prune (forceDelete) duyurulmaz: görsel zaten soft-delete anında duyurulmuştu.
     */
    protected static function booted(): void
    {
        $announce = function (self $image): void {
            if (self::$announceChanges && $image->product_id) {
                ProductImagesChanged::dispatch((int) $image->product_id);
            }
        };

        static::created($announce);
        static::updated($announce);
        static::restored($announce);
        static::deleted(function (self $image) use ($announce): void {
            if (! $image->isForceDeleting()) {
                $announce($image);
            }
        });
    }

    /**
     * Callback süresince görsel değişikliklerini duyurmaz — kendi olayını zaten fırlatan
     * akışlar (ProductService create/update → ProductCreated/Updated) için; aksi halde
     * Bagisto'ya 'updated' push'u 'created'dan ÖNCE kuyruğa girebilirdi.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutChangeAnnouncements(callable $callback): mixed
    {
        $previous = self::$announceChanges;
        self::$announceChanges = false;

        try {
            return $callback();
        } finally {
            self::$announceChanges = $previous;
        }
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Yalnızca eşikten eski soft-delete edilmiş kayıtları kalıcı siler (model:prune).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
