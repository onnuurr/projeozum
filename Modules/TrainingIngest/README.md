# TrainingIngest

Giydirme üretimlerinin (girdi + prompt + çıktı + insan kararı) ileride bir görsel
modelin fine-tune'unda kullanılmak üzere kalıcı, append-only şekilde toplanmasını
sağlayan bağımsız katman. **Şu an eğitim yapılmıyor** — yalnızca veri biriktiriliyor.

Ayrıntılı mimari/karar gerekçesi: `/root/.claude/plans/mutable-squishing-stonebraker.md`
(bu oturumda üretildi, referans için).

## Mimari özet

- Aynı Postgres instance'ında, SaaS'ın `public` şemasından ayrı bir `training` şeması.
- **Birincil yakalama yolu: event-driven.** `Modules/Creative` içine iki minimal event
  eklendi — `TryonGenerationRendered` (`Services/Ai/Support/`, Gemini'ye gönderilen nihai
  prompt'un hiçbir yerde kalıcı olmadığı tek an) ve `TryonReviewed` (`Events/`, onay/ret
  anı). `Modules/TrainingIngest` bunları dinler (`CaptureGenerationListener`,
  `CaptureReviewListener`, `ShouldQueue` — kullanıcı isteğini bloklamaz).
- **İkincil/telafi yolu:** `php artisan training:ingest` — yalnızca `creative_tryon_results`
  satırının O ANKİ durumunu görebilir (satır yerinde güncellenir, ara denemeler
  kurtarılamaz). Queue kesintisi sonrası telafi ve ilk kurulumda geçmiş veriyi bir
  kerelik aktarmak içindir.
- Yazma tek noktadan: `Modules\TrainingIngest\Services\IngestionService` — hem event
  listener'lar hem backfill CLI'ı bunu çağırır (kod tekrarı yok).

## Etkinleştirme

Aşağıdaki kurulum bitene kadar event hook'ları **kapalıdır** (`training-ingest.enabled`
config'i, varsayılan `false`) — canlıda her onay/ret işleminin henüz var olmayan bir DB
bağlantısına yazmaya çalışıp kuyrukta sessizce başarısız iş biriktirmesini önler.

1. `Modules/TrainingIngest/database/ops/create_training_db_roles.sql` script'ini
   **superuser/CREATEROLE yetkili** bir hesapla (hosting panelinin DB yönetimi ya da
   root `psql`) çalıştırın — uygulamanın kendi DB kullanıcısının bu yetkisi yok.
   Script `training_writer` (yalnızca `training` şemasına yazar) ve `training_reader`
   (yalnızca listelenen SaaS tablolarında `SELECT`) rollerini oluşturur.
   **Bu dosya `.gitignore`'dadır (şifre içerir), asla commit edilmez.**
2. `.env`'e aşağıdaki 4 satırı ekleyin (host/port/db zaten `DB_*`'e düşer):
   ```
   TRAINING_DB_USERNAME=training_writer
   TRAINING_DB_PASSWORD=<script'teki şifre>
   SAAS_DB_READONLY_USERNAME=training_reader
   SAAS_DB_READONLY_PASSWORD=<script'teki şifre>
   ```
3. Cloudflare R2'de **ayrı, yeni bir bucket** oluşturun (prod medya bucket'ından bilerek
   ayrı — kullanıcı kararı). Bucket'ta versiyonlama açın. `.env`'e:
   ```
   S3_ENDPOINT=...
   S3_BUCKET=...
   S3_ACCESS_KEY_ID=...
   S3_SECRET_ACCESS_KEY=...
   S3_REGION=auto
   ```
4. `php artisan module:migrate TrainingIngest` — `training` şeması + 12 tablo + 5
   raporlama view'i.
5. `.env`'de `TRAINING_INGEST_ENABLED=true` yapın, `config:clear` çalıştırın.
6. Queue worker'ın çalıştığından emin olun (`php artisan queue:work`) — yakalama
   listener'ları kuyruk üzerinden işlenir.

## Ortam değişkenleri

| Değişken | Açıklama |
|---|---|
| `TRAINING_DB_USERNAME` / `TRAINING_DB_PASSWORD` | `training` şemasına yazan rol (adım 1-2) |
| `TRAINING_DB_HOST` / `_PORT` / `_DATABASE` | Opsiyonel — verilmezse `DB_HOST`/`DB_PORT`/`DB_DATABASE`'e düşer (aynı instance) |
| `SAAS_DB_READONLY_USERNAME` / `_PASSWORD` | Backfill CLI'ının SaaS tablolarını okuduğu salt-okuma rol |
| `S3_ENDPOINT`, `S3_BUCKET`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_REGION` | Eğitim görselleri için ayrı R2 bucket'ı (prod `AWS_*` değişkenlerinden bilerek farklı isimlerde) |
| `TRAINING_INGEST_ENABLED` | `true` olmadan event listener'lar hiçbir şey yapmaz (varsayılan `false`) |

(`.env`/`.env.example` dosyalarına bu oturumdan doğrudan yazılamadı — dosya erişimi
izin ayarlarınca engellendi; yukarıdaki satırlar elle eklenmeli.)

## Komutlar

```
# Yedek/telafi — event yolunun kaçırdığı ya da ondan önceki (idempotent, tekrar
# çalıştırmak güvenli) satırları aktarır. rendered_prompt burada YENİDEN İNŞA edilir
# (orijinali hiçbir yerde saklı değildi) — birebir aynı olduğu garanti edilmez, bkz.
# TrainingIngestCommand docblock'u.
php artisan training:ingest --from=2026-08-01 --to=2026-08-19 [--dry-run] [--limit=500]

# Fine-tune için dataset çıkarımı — manifest.jsonl + görsel klasörü.
php artisan training:export --out=./dataset --verdict=approved [--from=] [--to=] [--category=mont]
```

Günlük cron örneği (`routes/console.php`, bu proje `Schedule::command()` convention'ını
kullanıyor — bkz. `creative:review-report`'un yanına eklenebilir):
```php
Schedule::command('training:ingest --from=' . now()->subDay()->toDateString() . ' --to=' . now()->toDateString())
    ->daily()
    ->onOneServer();
```

## Raporlama

`training` şemasında 5 salt-okuma view: `v_rejection_rate_by_prompt_version`,
`v_rejection_reasons`, `v_rejection_rate_by_category`, `v_rejection_rate_by_mannequin_pose`,
`v_approval_trend`. Bunlar mevcut `creative:review-report`'un **yerine geçmez**, onu besler.

## Bilinen sınırlamalar

- **`input_kind` tahminidir:** kaynak sistemde (`product_images`) açı/tip kolonu yok
  (yalnızca `is_cover`, `sort_order`). Posed manken görseli → `mannequin`, ana ürün
  görseli → `front`, ek referanslar → `detail` olarak eşlenir; gerçek front/back/side/
  seam/collar ayrımı kaynakta mevcut değil.
- **Backfill'in `rendered_prompt`'u yaklaşıktır**, orijinal üretim anındaki byte-birebir
  metin değildir (bkz. yukarıdaki komut açıklaması).
- **Ret taksonomisi prod'un anlık görüntüsüdür** (2026-08-19, 11 Türkçe etiket +
  `OTHER` düşüş kodu) — `creative_rejection_reasons`'a yeni bir sebep eklenirse buraya
  da ayrı bir migration ile eklenmesi gerekir (otomatik senkron değildir).

## Geri alma (rollback)

Migration'lar forward-only'dir (CLAUDE.md disiplini) — `down()`'lar gerçek tersini yapar
ama `training` şeması dolu bir haldeyken düşürülmez (bkz. `2026_08_19_000000_create_training_schema.php`
docblock'u): önce `pg_dump` ile yedek alın, gerekiyorsa manuel karar verin.

## Testler

`tests/Feature/TrainingIngest/IngestionServiceTest.php` — gerçek bir Postgres `training`
bağlantısı gerektirir (sqlite'ta çalışamaz, migration'lar orada no-op'tur). Bağlantı yoksa
testler otomatik `skipped` olur (hata değil) — kurulumdan sonra gerçek anlamda çalışır.
Her test kendi transaction'ında çalışıp sonunda rollback edilir.
