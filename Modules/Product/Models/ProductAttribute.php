<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global ürün özelliği (Ürün Ayarları → Özellikler). Kategoriler hangi özelliklerin geçerli
 * olduğunu seçer; ürün değeri products.attributes[code]'da tutulur (ProductAttributeService).
 * Bagisto'daki aynı kodlu attribute'un ana kaynağıdır — kod oluşturulduktan sonra değişmez.
 */
class ProductAttribute extends Model
{
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_PRICE = 'price';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_IMAGE = 'image';
    public const TYPE_FILE = 'file';

    /** Bagisto'daki attribute tipleriyle aynı adlar; tek fark `number` (Bagisto'da text + decimal doğrulama). */
    public const TYPES = [
        self::TYPE_SELECT, self::TYPE_MULTISELECT, self::TYPE_CHECKBOX, self::TYPE_TEXT, self::TYPE_TEXTAREA,
        self::TYPE_NUMBER, self::TYPE_PRICE, self::TYPE_BOOLEAN, self::TYPE_DATE, self::TYPE_DATETIME,
        self::TYPE_IMAGE, self::TYPE_FILE,
    ];

    /** Seçenek listesi olan tipler (değer: seçenek id'si / id dizisi). */
    public const OPTION_TYPES = [self::TYPE_SELECT, self::TYPE_MULTISELECT, self::TYPE_CHECKBOX];

    /** Bagisto'nun mağaza filtresine izin verdiği tipler (AttributeRepository::validateUserInput). */
    public const FILTERABLE_TYPES = [self::TYPE_SELECT, self::TYPE_MULTISELECT, self::TYPE_CHECKBOX, self::TYPE_BOOLEAN, self::TYPE_PRICE];

    /** Değeri yüklenen dosya olan tipler (products.attributes'ta Media diskindeki relative path). */
    public const UPLOAD_TYPES = [self::TYPE_IMAGE, self::TYPE_FILE];

    /**
     * KDV oranı: seçenek adları oran (%0, %1, %10, %20 …). Üründe seçilirse kategori oranını ezer
     * (VatRateService). Ürün formunda Fiyat sekmesinde gösterilir.
     */
    public const VAT_CODE = 'kdv_orani';

    /**
     * Sistem özellikleri: kategori ataması olmadan her kategoride geçerlidir, silinemez ve mağazaya
     * (Bagisto) attribute olarak gitmez — anlamı SaaS'ta işlenir (KDV → fiyat + vergi kategorisi).
     */
    public const SYSTEM_CODES = [self::VAT_CODE];

    /** Dosya klasörü (Media diski). */
    public const UPLOAD_DIR = 'product-attributes';

    /**
     * Bagisto'nun sistem attribute kodları ve ürün kaydında anlamı olan anahtarlar; SaaS
     * özelliği bu kodları alamaz (Bagisto'da çakışır ya da ürün verisini ezer).
     */
    public const RESERVED_CODES = [
        'sku', 'name', 'url_key', 'tax_category_id', 'new', 'featured', 'visible_individually', 'status',
        'short_description', 'description', 'price', 'cost', 'special_price', 'special_price_from',
        'special_price_to', 'meta_title', 'meta_keywords', 'meta_description', 'length', 'width', 'height',
        'weight', 'color', 'size', 'brand', 'guest_checkout', 'product_number', 'manage_stock', 'allow_rma',
        'rma_rule_id', 'kumas_icerigi', 'bakim_talimati', 'id', 'type', 'attribute_family_id', 'parent_id', 'categories', 'channels', 'locale',
        'channel', 'images', 'videos', 'variants', 'inventories', 'super_attributes', 'customer_group_prices',
        'up_sells', 'cross_sells', 'related_products', 'quantity', 'event', 'attributes',
    ];

    /** Ad yazımı: baş/son boşluk atılır, ardışık boşluklar teke iner. */
    public static function cleanName(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }

    /**
     * Mükerrer kontrolü anahtarı: Türkçe kurallarıyla küçük harf (İ→i, I→ı), boşluk normalize.
     * "Yaka Tipi", "yaka  tipi", "YAKA TİPİ" aynı anahtara düşer.
     */
    public static function nameKey(?string $name): string
    {
        return mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], self::cleanName($name)));
    }

    protected $table = 'product_attributes';

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_filterable',
        'sort_order',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'sort_order'    => 'integer',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ProductAttributeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product_attribute')->withPivot('required')->withTimestamps();
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }

    public function isUpload(): bool
    {
        return in_array($this->type, self::UPLOAD_TYPES, true);
    }

    public function isSystem(): bool
    {
        return in_array($this->code, self::SYSTEM_CODES, true);
    }
}
