# Trendyol Stub Fixtures

Dev/CI ortamında network çağrısı yapmadan Trendyol akışını doğrulamak için
kullanılan sahte payload'lar.

## Versiyon

- Trendyol Partner API: **v2** (2024-Q4 dokümantasyonu)
- Kaynak: https://developers.trendyol.com/

## Dosyalar

- `orders.json` — `GET /sapigw/suppliers/{supplierId}/orders` response örneği
- (eklenmesi gerekenler) `products.json`, `categories.json`, `webhook.json`

## Güncelleme

Provider dokümantasyonu değişirse:
1. Yeni payload örneğini buraya kopyala
2. README'deki versiyon bilgisini güncelle
3. `TrendyolOrderMapper` / `TrendyolService` testlerini çalıştır
