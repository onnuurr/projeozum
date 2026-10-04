<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * İş ortağının fatura / sevk / iade adresi — her türden en fazla bir tane
 * (unique tenant_id+type). Bagisto'ya bayi hesabının salt-okunur adresleri olarak gider.
 */
class TenantAddress extends Model
{
    public const TYPE_BILLING = 'billing';

    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_RETURN = 'return';

    public const TYPES = [self::TYPE_BILLING, self::TYPE_SHIPPING, self::TYPE_RETURN];

    public const LABELS = [
        self::TYPE_BILLING => 'Fatura adresi',
        self::TYPE_SHIPPING => 'Sevk adresi',
        self::TYPE_RETURN => 'İade adresi',
    ];

    /** Adresi oluşturan alanlar (tür ve tenant hariç) — form, kopyalama ve karşılaştırma bunu kullanır. */
    public const FIELDS = ['contact_name', 'phone', 'address', 'district', 'city', 'postal_code', 'country'];

    protected $fillable = [
        'tenant_id',
        'type',
        'contact_name',
        'phone',
        'address',
        'district',
        'city',
        'postal_code',
        'country',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Tek satırlık okunur hâl: "Cadde no, İlçe / İl 52000". */
    public function oneLine(): string
    {
        $place = collect([$this->district, $this->city])->filter()->implode(' / ');

        return trim(collect([$this->address, trim($place.' '.$this->postal_code)])->filter()->implode(', '));
    }
}
