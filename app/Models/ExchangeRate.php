<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bir para biriminin bir bülten günündeki TCMB kuru (1 birim = X TL; `unit` > 1 olan para
 * birimlerinde, örn. JPY 100, kur `unit` adedi içindir — ExchangeRateService birime böler).
 */
class ExchangeRate extends Model
{
    public const TYPE_FOREX_BUYING = 'forex_buying';
    public const TYPE_FOREX_SELLING = 'forex_selling';
    public const TYPE_BANKNOTE_BUYING = 'banknote_buying';
    public const TYPE_BANKNOTE_SELLING = 'banknote_selling';

    public const TYPES = [
        self::TYPE_FOREX_BUYING, self::TYPE_FOREX_SELLING, self::TYPE_BANKNOTE_BUYING, self::TYPE_BANKNOTE_SELLING,
    ];

    protected $fillable = [
        'currency_code',
        'rate_date',
        'unit',
        'forex_buying',
        'forex_selling',
        'banknote_buying',
        'banknote_selling',
        'source',
    ];

    protected $casts = [
        'rate_date'        => 'date',
        'unit'             => 'integer',
        'forex_buying'     => 'decimal:6',
        'forex_selling'    => 'decimal:6',
        'banknote_buying'  => 'decimal:6',
        'banknote_selling' => 'decimal:6',
    ];

    /** 1 birim döviz kaç TL (`unit`'e bölünmüş). */
    public function perUnit(string $type = self::TYPE_FOREX_SELLING): ?float
    {
        $value = $this->{$type};

        return $value === null ? null : (float) $value / max(1, $this->unit);
    }
}
