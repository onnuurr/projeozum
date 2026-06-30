# Medya CDN Kurulumu (Cloudflare R2)

Görseller ve dosya çıktıları **env-driven** bir medya diski üzerinden sunulur:

- **Local (Laragon):** `MEDIA_DISK` set edilmez → `public` diski → `http://localhost/storage/...`.
  Mevcut davranış değişmez; `php artisan storage:link` gereklidir.
- **Canlı (production):** `MEDIA_DISK=s3` → Cloudflare R2 + CDN.

DB'de yalnızca **relative path** (örn. `products/1/x.png`) tutulur; tam URL okuma
anında `App\Support\Media::url()` ile aktif diske göre üretilir. Bu yüzden host hiçbir
zaman satırlara gömülmez ve CDN host'u değişse bile DB'ye dokunulmaz.

## 1) `.env` (production)

`.env.example` repo'da korumalı olduğundan değişkenler burada belgelenir. Canlı
`.env`'e şu bloğu ekleyin:

```dotenv
# Tüm modüllerin medya diski (Product + Creative + Atelier).
MEDIA_DISK=s3
# İsterseniz modül bazında override edilebilir; boş bırakılırsa MEDIA_DISK kullanılır.
# CREATIVE_DISK=s3
# ATELIER_CONCEPT_DISK=s3
# ATELIER_CONVERSION_DISK=s3

# Cloudflare R2 (S3-uyumlu) — Laravel'in hazır 's3' driver'ı kullanılır.
AWS_ACCESS_KEY_ID=<r2-access-key-id>
AWS_SECRET_ACCESS_KEY=<r2-secret-access-key>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket-adi>
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
# CDN/public taban URL'i — Media::url() bunu önekler. Sonunda / olmamalı.
AWS_URL=https://cdn.ornek.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Local `.env`'de **bu bloğu eklemeyin** (özellikle `MEDIA_DISK` set etmeyin); sistem
`public` diske düşer ve `http://localhost/storage` ile çalışır.

## 2) R2 / CDN tarafı (altyapı)

1. Cloudflare R2'de bir bucket oluşturun (`AWS_BUCKET`).
2. R2 API token üretin (Object Read & Write) → `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`.
3. Bucket'a **public erişim** verin: R2'nin "Public Development URL"ü veya tercihen bir
   **custom domain** (örn. `cdn.ornek.com`) bağlayın ve Cloudflare CDN/cache açık olsun.
4. `AWS_URL` değerini bu public/CDN domain'ine ayarlayın.
5. Görseller `public` görünürlükle yazılır; bucket okumaya açık olmalıdır.

## 3) Deploy notları

- `php artisan config:cache` sonrası env değişiklikleri geçerli olur.
- `php artisan storage:link` adımı R2'de no-op'tur; build script'inde kalması zararsız.
- Render motoru (Python) yalnız yerel dosya okur; uzak diskte (R2) girdi dosyaları
  (SVG şablon, marka logo/font, ürün/manken/poz görselleri, kalıp PDF'leri) otomatik
  olarak `storage/app/private/media-cache/` altına indirilip oradan okunur
  (`App\Support\Media::localPath()`). Bu klasör yazılabilir olmalıdır.

## 4) Mevcut veriyi taşıma (tek seferlik, prod)

`MEDIA_DISK=s3`'e geçmeden önce mevcut `storage/app/public` içeriğini bucket'a
kopyalayın (yapı korunur):

```bash
# rclone örneği (R2 remote'u "r2" olarak tanımlı varsayılır)
rclone copy storage/app/public r2:<bucket-adi> --progress
```

DB tarafında ek bir işlem gerekmez: `product_images.url` kolonu
`2026_06_30_120000_rename_product_images_url_to_path_and_normalize` migration'ı ile
`path`'e çevrilip relative'e normalize edilir; diğer modeller (`CreativeAsset.image_path`,
`Pattern.*_path`, vb.) zaten relative tutuyordu.
