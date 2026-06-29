---
name: laravel-model
description: Use when creating or editing an Eloquent model in this project (app/Models/ or Modules/*/Models/). Covers fillable/casts, SoftDeletes + Prunable 30-day pattern for log-like tables, relationships, scopes, and registering prunable models in routes/console.php.
---

# Laravel Model — Proje Konvansiyonu

## Konum

- Modüle ait domain modeli → `Modules/<Mod>/Models/<X>.php`, namespace `Modules\<Mod>\Models`
- Paylaşılan (User, Tenant pivot vb.) → `app/Models/`

## İskelet

```php
namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes; // sadece gerekirse — aşağıda kararı oku

    protected $table = 'products';

    protected $fillable = ['category_id', 'brand_id', 'name', /* ... */];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_new'       => 'boolean',
        'review_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

- **`$guarded` kullanma; `$fillable`** kullan.
- **Casts** her zaman tanımla: para `decimal:N`, sayım/adet `integer`, bayrak `boolean`,
  enum string'ler PHP `enum` cast'i veya plain string.
- İlişkilerin **return type'ı yazılır** (`BelongsTo`, `HasMany`, `MorphTo`...).
- Custom scope varsa `scopeXxx(Builder $q): Builder` imzasıyla.

## Soft-delete kararı

- **Domain "olay" / "log" tipi** (hareketler, görseller, fiyat listesi kayıtları) →
  `SoftDeletes` + **Prunable + 30 gün** (aşağı bak).
- **Asıl iş varlığı** (`Product`, `Tenant`, `Warehouse`) → SoftDeletes
  **kullanılır ama Prunable yok**; düşük hacimde ve geri-getirme değeri var.
- **Hiç delete olmuyorsa** → SoftDeletes ekleme; gereksiz `deleted_at` kolonu açma.

## Prunable deseni (log-like için zorunlu)

Eşik 30 gün, sabit olarak modele konur. Örnek:
`Modules/Product/Models/StockMovement.php`

```php
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use Prunable;
    use SoftDeletes;

    public const PRUNE_AFTER_DAYS = 30;

    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
```

**Sonra:** `routes/console.php`'deki `model:prune` listesine ekle:

```php
Schedule::command('model:prune', [
    '--model' => [
        ProductImage::class,
        Stock::class,
        StockMovement::class,
        PriceList::class,
        // YENİ MODELİNİ BURAYA EKLE
    ],
])->daily()->onOneServer()->runInBackground();
```

Modellerin **otomatik keşfi yalnız `app/Models`'a bakar**; modül modelleri için
FQCN açıkça verilmesi şarttır.

## İlişki defteri

| İlişki | Yer | Örnek |
|---|---|---|
| BelongsTo | sahip tarafta | `warehouse(): BelongsTo` |
| HasMany | parent tarafta | `variants(): HasMany` |
| BelongsToMany | iki taraf da | pivot tablosu lazım |
| MorphTo / MorphMany | polimorfik referans (StockMovement.reference) | `reference(): MorphTo` |

Eager loading varsayılan değil; controller'lar `with(...)` ile yükler — modelde
`$with` kullanma.

## Hook'lar

Domain davranışı için `booted()` içinde:
- Saved/created hook'u → bkz. `Modules/Product/Models/PriceList.php`
  (retail değiştiğinde variant.price'a senkronize)

Karmaşıklaşırsa observer dosyasına çıkar (`Modules/<Mod>/Observers/`).

## Çoklu tenant (Tenant scope'u)

Çoklu kiracıya açık tablolarda **global scope yerine `accessibleToTenant()`
benzeri explicit local scope** kullanılır. Controller / Service çağırmadıkça
filtre devreye girmez — yetki kontrolünü unutmamak için.

## Checklist

- [ ] Doğru dizin / namespace
- [ ] `$fillable` (asla `$guarded`)
- [ ] `casts` tüm tip-anlamlı kolonlar için tanımlı
- [ ] İlişki metotlarının return type'ı yazıldı
- [ ] SoftDeletes kararı verildi (gerek var mı?)
- [ ] Log-like ise: Prunable + 30 gün eşik **+** `routes/console.php`'ye eklendi
- [ ] Yeni global scope eklemedim; tenant filtresi açık scope
- [ ] Karmaşık hook observer'a çıkartıldı
