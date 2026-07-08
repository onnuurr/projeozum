# Trendyol e-Faturam API — Entegrasyon Referansı

> `TrendyolEFaturamProvider` sürücüsünün dayandığı API sözleşmesi. Kaynak: Trendyol
> geliştirici dokümanının kaydedilmiş HTML sayfaları (`trendyol api/` klasörü).
> Tüm bilgiler dokümandan DOĞRULANMIŞTIR.

## 0) Ortam & genel kurallar
| Ortam | Gateway base URL |
|-------|------------------|
| Stage (test) | `https://stage-apigateway.trendyolefaturam.com` |
| Production   | `https://apigateway.trendyolecozum.com` (dikkat: farklı alan adı) |

- **Tutarlar KURUŞ (integer):** 114.55 TL → `11455`. `createOutgoingEInvoice`, `createEArchive` gövdelerindeki tüm tutar alanları.
- Hata formatı **RFC 7807** (`application/problem+json`): `type`, `title`, `status`, `detail`, `instance`.
- `.env`: `FINANCE_EINVOICE_EMAIL`, `FINANCE_EINVOICE_PASSWORD`, `FINANCE_EINVOICE_COMPANY_ID`,
  `FINANCE_EINVOICE_BASE_URL` (prod'da `apigateway.trendyolecozum.com`).

## 1) Sign-in — `POST /api/auth/signin`
İstek: `{ "email", "password" }`.
**Token gövdede değil, response HEADER'larında döner:**
- `x-access-token` (JWT) → sonraki tüm isteklerde `x-access-token: <token>` header'ı olarak gönderilir.
- `x-refresh-token` → yenileme için.

Sürücü token'ı `finance.einvoice.trendyol.token` cache key'inde `token_ttl` (varsayılan 3000 sn) tutar.

## 2) Mükellef sorgu — `GET /api/invoice/taxpayers/:taxId`
VKN/TCKN e-Fatura mükellefi mi? Yanıt bir dizi:
`[{ taxId, alias, title, gibUserType, postBoxType, aliasType: "INVOICE"|"DESPATCH_ADVICE", ... }]`
- Boş olmayan dizi (200) → **kayıtlı → e-Fatura**.
- 404 / boş → **kayıtlı değil → e-Arşiv**.

## 3) e-Fatura oluştur — `POST /api/invoice/documents/outgoing-einvoice`
## 3b) e-Arşiv oluştur — `POST /api/invoice/documents/earchive`
İkisi de aynı gövde şemasını kullanır (fark: `invoiceInfo.invoiceType` = `EFATURA` / `EARSIVFATURA`).
Sürücünün gönderdiği alt küme (tam şema medical/export/withholding vb. içerir — kullanılmıyor):
```jsonc
{
  "source": "PORTAL",
  "companyId": <int>,
  "recipientInfo": { "taxId", "countryCode": "TR", "address", "name" },
  "currencyInfo": { "currency": "TRY" },
  "invoiceInfo": { "invoiceType": "EFATURA|EARSIVFATURA", "invoiceTypeCode": "SATIS" },
  "invoiceLines": [{
    "unitCode": "C62", "quantity": 2, "itemName": "...",
    "unitPriceAmount": 25000, "taxableAmount": 50000, "taxPercent": 20,
    "taxAmount": 10000, "totalAmount": 60000,
    "totalTax": { "totalTaxAmount": 10000,
      "subTotalTaxes": [{ "taxableAmount": 50000, "taxAmount": 10000, "taxType": "KDV", "percent": 20, "name": "KDV" }] }
  }]
}
```
**Yanıt (200):** `{ id, invoiceUuid, invoiceId, status, gibStatusCode, currency, source, ... }`
→ UUID `invoiceUuid` alanında; başlangıç `status` genelde `10` (İşleniyor).

## 4) Durum sorgu
- e-Fatura: `GET /api/invoice/documents/outgoing-einvoice/status/:invoiceUuid` → `{ status, gibStatusCode, invoiceUuid }`
- e-Arşiv: `GET /api/invoice/documents/earchive/status/:invoiceUuid` → `{ status, gibStatus, invoiceUuid }`

## 5) İptal
- e-Arşiv: `POST /api/invoice/documents/earchive/cancel` gövde `{ invoiceUuid, companyId }`. 200 = iptal edildi; 409 = iptal edilemez.
- **e-Fatura: API'de tekil iptal ucu YOK** (karşı taraf reddi / GİB senaryosu). Sürücü `cancel()` e-Fatura için `false` döner.

## 6) Statü kodları (`Fatura Statü Kodları`) → efatura_status eşlemesi
| Kod | Anlam | efatura_status |
|-----|-------|----------------|
| 10 | İşleniyor | pending |
| 20 | Doküman Hazırlanıyor | pending |
| 29 | Döküman Hatası | **failed** |
| 30 | Oluşturuldu | pending |
| 40 | GİB'e Gönderildi | pending |
| 50 | Yanıt Bekleniyor | pending |
| 100 | Reddediliyor | pending |
| 105 | Reddedildi | **failed** |
| 200 | Onaylanıyor | pending |
| 205 | Onaylandı (nihai başarı) | **success** |
| 305 | İptal Edildi | **failed** (iptal; raw'da 305 saklanır) |
| 405 | Hatalı | **failed** |

## 7) Bilinen sınırlar / sonraki adımlar
- `OutgoingInvoice` alıcı alanları sınırlı (city/district/email/phone/taxOffice yok). e-Arşiv'de
  e-posta teslimi için `recipientInfo.email` gerekebilir → ileride buyer alanları genişletilmeli.
- `invoiceType` değerleri (`EFATURA`/`EARSIVFATURA`) config'te; "Enum Değerleri" sayfası kaydedilirse
  senaryo kodları (TEMEL/TICARI) netleştirilebilir.
- Gerçek uçtan uca doğrulama stage kimlik bilgisiyle yapılmalı (bu ortam Cloudflare'e takılıyor;
  `.env`'e stage email/password + `queue:work` ile test edilmeli).
