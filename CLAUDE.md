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

## DTO kullanım istisnaları

Projenin genel kuralı (`laravel-service` skill): **DTO kullanılmaz**, servisler doğrudan
Eloquent model/Collection döner. Bunun iki sanctioned istisnası var — her ikisi de bir dış
sınırda (dış API entegratörü / bağımsız modül) Eloquent model sızdırmamak için:

1. `Modules/Finance/DTO/EInvoiceSendResult.php` — e-Fatura entegratörü dönüş değeri.
2. `Modules/Marketplace/DTOs/*` (`MarketplaceCredentials`, `ProductPushDTO`,
   `ProductVariantPushDTO`, `MarketplaceOrderDTO`, `MarketplaceOrderLineDTO`, `PushResult`,
   `ReportResult`) — `Modules/Marketplace` hiçbir modüle (Tenant/Product dahil) bağımlı
   olmaması gerektiği için (bkz. modülün `module.json` açıklaması) Eloquent model yerine
   bu DTO'ları kullanır; `Modules/Tenant` bunları besleyen taraf.

Yeni bir modül sınırında benzer bir izolasyon ihtiyacı doğarsa aynı desen kullanılabilir —
ama yine bu listeye eklenmeli. Bunların dışında yeni DTO eklemeden önce bu dosya güncellenir.
