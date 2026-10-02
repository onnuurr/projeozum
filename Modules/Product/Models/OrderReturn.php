<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bayi iade talebi. Talep + atölye ekspertizi Bagisto'dan (RMA) gelir; karar, iade faturası
 * kontrolü ve para iadesi SaaS'ta yapılır. Akış:
 *
 *   requested → approved → (iade faturası: pending → accepted) → refunded
 *            ↘ rejected                                         (Bagisto'da iptal → cancelled)
 *
 * İş kuralları Modules\Bagisto\Services\BagistoReturnService'te.
 *
 * `tenant()` ilişkisi bilerek yok: Product modülü Tenant'a bağımlı olamaz (deptrac). Bayi
 * tenant_id üzerinden Bagisto/Finance tarafında çözülür (BagistoReturnService::tenantOf).
 */
class OrderReturn extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_REFUNDED  = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    // İade faturası (yalnız invoice_required iken): bayinin yüklemesi bekleniyor → yüklendi, kontrolde → kabul / red (yeniden yükler).
    public const INVOICE_AWAITING = 'awaiting';
    public const INVOICE_PENDING  = 'pending';
    public const INVOICE_ACCEPTED = 'accepted';
    public const INVOICE_REJECTED = 'rejected';

    public const REFUND_CARI = 'cari';
    public const REFUND_CARD = 'card';
    public const REFUND_BANK = 'bank';

    protected $table = 'order_returns';

    protected $fillable = [
        'order_id',
        'tenant_id',
        'bagisto_rma_id',
        'bagisto_order_id',
        'status',
        'bagisto_status',
        'reason',
        'resolution',
        'information',
        'cargo_method',
        'items',
        'images',
        'inspection',
        'requested_amount',
        'approved_amount',
        'decision_note',
        'decided_by',
        'decided_at',
        'invoice_required',
        'invoice_status',
        'invoice_no',
        'invoice_date',
        'invoice_amount',
        'invoice_path',
        'invoice_uploaded_at',
        'invoice_review_note',
        'invoice_reviewed_by',
        'invoice_reviewed_at',
        'refund_method',
        'refund_reference',
        'refunded_by',
        'refunded_at',
        'bagisto_created_at',
    ];

    protected $casts = [
        'items'               => 'array',
        'images'              => 'array',
        'inspection'          => 'array',
        'requested_amount'    => 'decimal:2',
        'approved_amount'     => 'decimal:2',
        'invoice_amount'      => 'decimal:2',
        'invoice_required'    => 'boolean',
        'invoice_date'        => 'date',
        'decided_at'          => 'datetime',
        'invoice_uploaded_at' => 'datetime',
        'invoice_reviewed_at' => 'datetime',
        'refunded_at'         => 'datetime',
        'bagisto_created_at'  => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_REQUESTED => __('product::labels.return_status.requested'),
            self::STATUS_APPROVED  => __('product::labels.return_status.approved'),
            self::STATUS_REJECTED  => __('product::labels.return_status.rejected'),
            self::STATUS_REFUNDED  => __('product::labels.return_status.refunded'),
            self::STATUS_CANCELLED => __('product::labels.return_status.cancelled'),
        ];
    }

    public static function invoiceStatuses(): array
    {
        return [
            self::INVOICE_AWAITING => 'Bayiden Bekleniyor',
            self::INVOICE_PENDING  => 'Kontrol Bekliyor',
            self::INVOICE_ACCEPTED => 'Kabul Edildi',
            self::INVOICE_REJECTED => 'Reddedildi',
        ];
    }

    public static function refundMethods(): array
    {
        return [
            self::REFUND_CARI => 'Cari hesaba alacak',
            self::REFUND_CARD => 'Karta iade (iyzico)',
            self::REFUND_BANK => 'Havale / EFT',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function invoiceReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoice_reviewed_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /** Karar verilmiş ama parası henüz iade edilmemiş (açık iş) talepler. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_REQUESTED, self::STATUS_APPROVED]);
    }

    /** Fatura şartı varsa kabul edilmiş olmalı; para iadesi butonunun ön koşulu. */
    public function invoiceCleared(): bool
    {
        return ! $this->invoice_required || $this->invoice_status === self::INVOICE_ACCEPTED;
    }
}
