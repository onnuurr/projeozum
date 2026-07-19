<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Giysi parça/tip tespitlerinin kanonik adlandırma sözlüğü (yaka, cep, etek vb.).
 * {@see \Modules\Creative\Services\GarmentScanService} yeni bir etiket anahtarı
 * gördüğünde burada firstOrCreate ile eşleşir/oluşturur — "otomatik isimlendirip
 * veritabanında saklama" gereksinimi budur.
 *
 * preservation_category/default_priority bir PARÇA ADININ (örnekten bağımsız)
 * sabit korunma sınıfını taşır — {@see \Modules\Creative\Services\GarmentIdentityRuleEngine}
 * bunu Gemini'nin örnek-bazlı değerlendirmesiyle birleştirip nihai önceliği
 * hesaplar (bkz. ROADMAP.md Faz G.5).
 */
class GarmentLabel extends Model
{
    public const GROUP_PART = 'part';

    public const GROUP_GARMENT_TYPE = 'garment_type';

    public const GROUP_ANGLE = 'angle';

    public const SOURCE_SEED = 'seed';

    public const SOURCE_AUTO_CREATED = 'auto_created';

    public const CATEGORY_IDENTITY = 'identity';

    public const CATEGORY_APPEARANCE = 'appearance';

    public const CATEGORY_CONSTRUCTION = 'construction';

    public const CATEGORY_HARDWARE = 'hardware';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    /** Rule Engine'de sıralama/karşılaştırma için — index büyüdükçe öncelik artar. */
    public const PRIORITY_ORDER = [
        self::PRIORITY_LOW      => 0,
        self::PRIORITY_MEDIUM   => 1,
        self::PRIORITY_HIGH     => 2,
        self::PRIORITY_CRITICAL => 3,
    ];

    protected $table = 'creative_garment_labels';

    protected $fillable = ['key', 'display', 'group', 'source', 'preservation_category', 'default_priority'];
}
