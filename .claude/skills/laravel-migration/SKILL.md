---
name: laravel-migration
description: Use when writing a Laravel migration in this project (under database/migrations/ or Modules/*/database/migrations/). Codifies the strict DB discipline from CLAUDE.md — forward-only migrations, real down(), staged table drops, schema:audit run after.
---

# Laravel Migration — Proje Konvansiyonu

> **Bu projede DB disiplini sıkıdır.** CLAUDE.md'nin "Veritabanı değişiklik
> disiplini" bölümü kuraldır; bu skill o kuralın yürütülebilir hâlidir.

## Konum

- Modül-özgü tablo değişikliği → `Modules/<Mod>/database/migrations/`
- Core / paylaşılan tablo → `database/migrations/`
- Dosya adı: `YYYY_MM_DD_HHMMSS_<aciklayici_eylem>.php` (kronolojik sıralama önemli).

## Mutlak kurallar

1. **Prod'a gitmiş migration'ı düzenleme.** Üstüne yeni bir migration ekle.
2. **`down()` boş bırakılmaz.** Her `up()` adımının tersi yazılır
   (kolon eklediysen `dropColumn`, tablo oluşturduysan `dropIfExists`).
3. **Özellik kaldırınca migration zorunlu.** "Kodu sil, DB'de bırak" yasaktır —
   şişmenin ana sebebi budur.
4. **Tablo silme kademeli.** Doğrudan `dropIfExists` yapma:
   - **Aşama A** — kullanım kodunu kaldır, tabloyu `_deprecated_<YYYY_MM_DD>` olarak rename.
   - **Aşama B** — bir-iki sürüm sonra `dropIfExists` ile gerçek silme.
   - Aşama A'dan önce yedek: `pg_dump --table=<adı> ... > backup.sql`.

## Referans desen

`Modules/Product/database/migrations/2026_05_17_140100_drop_legacy_variant_tables.php`

```php
public function up(): void
{
    Schema::dropIfExists('product_sizes');
    Schema::dropIfExists('product_colors');

    Schema::table('products', function (Blueprint $table) {
        if (Schema::hasColumn('products', 'stock')) {
            $table->dropColumn('stock');
        }
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->unsignedInteger('stock')->default(0)->after('old_price');
    });

    Schema::create('product_sizes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        $table->string('size', 16);
        $table->unsignedInteger('sort_order')->default(0);
        $table->unique(['product_id', 'size']);
        $table->index('size');
    });
    // ...
}
```

Dikkat çekenler:
- **Defansif** `Schema::hasColumn(...)`: re-run'da hata vermez.
- `down()` veriyi geri **getirmiyor**; sadece şemayı geri kuruyor — bu yeterlidir,
  veri kurtarma migration'ın sorumluluğu değil (yedekten döner).
- `down()`'da indeksler, unique'ler, FK'ler **gerçekten** yeniden tanımlanmış.

## Postgres ipuçları

- DB **PostgreSQL** (`search_path: 'public'`). Tablo arama gerektiğinde
  `public.` prefixini kaldırmayı unutma (bkz. `SchemaAuditCommand`).
- Enum yerine `string` + check constraint veya kontrolü model katmanında yap.
- Büyük indeks değişikliklerinde `CONCURRENTLY` gerekebilir; raw SQL gerekiyorsa
  `DB::statement(...)` ile yaz ve `down()`'da tersini ekle.

## Çalıştırma

```bash
# Modül migration'u
php artisan module:migrate <ModulAdi>

# Tek tek geri alma testi (dev)
php artisan migrate:rollback --step=1

# DEĞİŞİKLİKTEN SONRA ZORUNLU: orphan / suspect kolon var mı?
php artisan schema:audit
# çıktı: storage/app/schema-audit.json
```

`schema:audit` salt-okur; sadece raporlar. Çıktıdaki `orphan_tables` veya
`suspect_columns` doluysa **commit etmeden önce** sebebini ya çöz (whitelist'e ekle
veya migration'ı tamamla) ya da rapora git mesajıyla referans ver.

## Soft-delete'li yeni tablo eklerken

`softDeletes()` ekledin mi? O zaman `laravel-model` skill'inde anlatılan **Prunable**
desenini de modelde uygula ve `routes/console.php`'deki `model:prune` schedule'ına ekle.

## Checklist

- [ ] Konum doğru (modül vs root)
- [ ] Dosya adı timestamp + açıklayıcı
- [ ] `up()` re-run güvenli (`hasColumn`, `hasTable` defansif kontrolleri)
- [ ] `down()` gerçek ters işlemi yapıyor (indeks/unique/FK dahil)
- [ ] Tablo silme kademeli (yedek → rename `_deprecated_<tarih>` → ileride drop)
- [ ] Kaldırılan özelliğin ilgili kolonu/tablosu bu migration'da temizlendi
- [ ] `php artisan schema:audit` çalıştırıldı, çıktı temiz / açıklandı
- [ ] Soft-delete'li yeni tablo eklediysem model + schedule eklendi
