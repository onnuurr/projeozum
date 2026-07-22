<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ret ekranındaki hazır seçim maddesi (örn. "Düzeltilmesi gereken alan" altında
 * Göz, Burun, Gülüş). Superadmin panelinden yönetilir; reddedilen görselde
 * yönetici bunları işaretler ve seçim ReviewChatService'e otomatik aktarılır.
 *
 * Düşük hacimli iş varlığı: Prunable eklenmez (bkz. CLAUDE.md md.5). SoftDeletes
 * ile "çıkarma" geri-dönülebilir ve geçmiş retlerdeki etiketler bozulmaz.
 */
class RejectionReason extends Model
{
    use SoftDeletes;

    protected $table = 'creative_rejection_reasons';

    /**
     * Ret diyaloğunun çıktığı ekranlar. NULL context ("Tüm ekranlar") bu üçünde
     * de gösterilir; belirli bir değer yalnız o ekranda gösterilir.
     */
    public const CONTEXTS = [
        'gallery'    => 'Creative Galerisi',
        'mannequin'  => 'Manken',
        'tryon'      => 'Model Giydirme',
    ];

    protected $fillable = [
        'category',
        'context',
        'label',
        'hint',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForContext(Builder $q, string $context): Builder
    {
        return $q->where(function (Builder $q) use ($context) {
            $q->whereNull('context')->orWhere('context', $context);
        });
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('category')->orderBy('sort_order')->orderBy('id');
    }
}
