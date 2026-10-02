<?php

namespace Modules\Bagisto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Bayi portalında (Bagisto) onaylatılan bir sözleşme / onay metni. Metnin kendisi sürümlerde
 * (DealerAgreementVersion) tutulur; güncel metin, o dilde en son yayımlanan sürümdür. Her dil ayrı yayımlanır.
 * Bayi portalına şimdilik yalnızca LIVE_LOCALES (Türkçe) gider; İngilizce hazırlanıp yayımlanabilir, bekleme aşamasındadır. Kayıtlar migration ile
 * açılır (anahtarlar Bagisto şablonlarında sabit), yönetim ekranı yalnızca yeni sürüm yayımlar.
 */
class DealerAgreement extends Model
{
    public const DISPLAY_DOCUMENT = 'document';

    public const DISPLAY_CONSENT = 'consent';

    public const DEFAULT_LOCALE = 'tr';

    /** Desteklenen sözleşme dilleri. */
    public const LOCALES = ['tr' => 'Türkçe', 'en' => 'İngilizce'];

    /** Bayi portalında gösterilen diller (İngilizce bekleme aşamasında). */
    public const LIVE_LOCALES = ['tr'];

    protected $fillable = ['parent_id', 'annex_number', 'key', 'title', 'display', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'annex_number' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Ekin bağlı olduğu ana sözleşme (ek değilse null). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Yürürlükteki ekler (Ek-1, Ek-2 … sırasıyla). */
    public function annexes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('is_active', true)->orderBy('annex_number');
    }

    public function isAnnex(): bool
    {
        return $this->parent_id !== null;
    }

    /** "Ek-1" (İngilizce metinde "Schedule 1"). */
    public function annexLabel(string $locale = self::DEFAULT_LOCALE): ?string
    {
        if (! $this->annex_number) {
            return null;
        }

        return ($locale === 'en' ? 'Schedule ' : 'Ek-').$this->annex_number;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DealerAgreementVersion::class)->latest('published_at')->latest('id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(DealerAgreementDraft::class);
    }

    /** Türkçe güncel sürüm (bayi portalının varsayılan dili). Diğer diller için DealerAgreementService::current(). */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DealerAgreementVersion::class)->ofMany(
            ['published_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('locale', self::DEFAULT_LOCALE),
        );
    }
}
