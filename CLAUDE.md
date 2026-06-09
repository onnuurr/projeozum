# Proje Kuralları

## Veritabanı değişiklik disiplini

Geçmişte beğenilmeyip kaldırılan özelliklerin tablo/kolon kalıntıları temizlenmediği için
veritabanı şişti (bkz. `php artisan schema:audit`). Tekrarını önlemek için:

1. **Özellik kaldırınca mutlaka migration yaz.** Bir kolon/tablo artık kullanılmıyorsa onu
   kaldıran ileri-tarihli bir migration yazılır. "Kodu sil, tabloyu DB'de bırak" yasaktır —
   kalıntının ana sebebi budur.
2. **Prod'a gitmiş migration'ı düzenleme.** Çalışmış bir migration sonradan değiştirilmez;
   her zaman üstüne yeni bir migration eklenir.
3. **`down()` gerçek ters işlemi yapsın.** Her migration'ın `down()` metodu `up()`'ı tersine
   çevirmelidir; boş bırakılmaz. Örnek desen:
   `Modules/Product/database/migrations/2026_05_17_140100_drop_legacy_variant_tables.php`.
4. **Tablo silme kademeli ve geri-dönülebilir olsun.** Doğrudan DROP etme:
   önce yedek (`pg_dump`), sonra `_deprecated_<tarih>` olarak rename (karantina),
   bir-iki sürüm sonra gerçek `dropIfExists`. (Bu PostgreSQL veritabanıdır.)
5. **Yeni soft-delete'li model eklerken `Prunable` değerlendir.** Büyüyen/log niteliğindeki
   tablolara `Illuminate\Database\Eloquent\Prunable` + `prunable()` eşiği eklenir
   (örnek: `Modules/Product/Models/StockMovement.php`). Düşük hacimli iş varlıkları
   (örn. `Tenant`) bilerek korunur.

### Bakım araçları
- `php artisan schema:audit` — DB'yi modellerle karşılaştırıp orphan tablo/kolon ve şişme
  raporu üretir (salt-okuma). Çıktı: `storage/app/schema-audit.json`.
- Retention zamanlamaları `routes/console.php` içindedir (`model:prune`, `queue:prune-*`,
  `auth:clear-resets`, `cache:prune-stale-tags`). Sunucuda `schedule:run` cron'u gerekir.

> Not: Çalışan veritabanı geçmiş özellik kuşaklarından dolayı repo migration'larıyla tam
> uyumlu olmayabilir; şema değişikliği yapmadan önce `schema:audit` ile mevcut durumu doğrula.
