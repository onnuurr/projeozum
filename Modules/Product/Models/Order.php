<?php

namespace Modules\Product\Models;

use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tenant\Models\Tenant;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /** @deprecated Tenant-only B2B'de B2C sipariş tipi kullanılmıyor; Faz 4'te silinecek. Yeni kod bu sabite YAZMAMALI. */
    public const TYPE_B2C      = 'b2c';
    public const TYPE_DROPSHIP = 'dropship';

    // Sipariş durum makinesi (D3).
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Durum sabiti → TR etiket haritası. Vue'ya props ile geçirilir (tek kaynak).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING   => 'Beklemede',
            self::STATUS_CONFIRMED => 'Onaylandı',
            self::STATUS_PREPARING => 'Hazırlanıyor',
            self::STATUS_SHIPPED   => 'Kargolandı',
            self::STATUS_DELIVERED => 'Teslim Edildi',
            self::STATUS_CANCELLED => 'İptal Edildi',
        ];
    }

    /**
     * İzin verilen durum geçişleri (D3). delivered/cancelled terminaldir.
     *
     * @return array<string, array<int, string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_PENDING   => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
            self::STATUS_PREPARING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED   => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'tenant_id',
        'shipping_info',
        'payment_method',
        'note',
        'subtotal',
        'shipping_fee',
        'total',
        'discount_rate',
        'discount_amount',
        'due_date',
        'status',
        'order_type',
    ];

    protected $casts = [
        'shipping_info'   => 'array',
        'subtotal'        => 'decimal:2',
        'shipping_fee'    => 'decimal:2',
        'total'           => 'decimal:2',
        'discount_rate'   => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'due_date'        => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function scopeDropship(Builder $query): Builder
    {
        return $query->where('order_type', self::TYPE_DROPSHIP);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
