<?php

namespace Modules\Finance\Services\EInvoice;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\DTO\EInvoiceSendResult;
use Modules\Finance\DTO\EInvoiceStatusResult;
use Modules\Finance\Models\OutgoingInvoice;
use RuntimeException;

/**
 * Trendyol e-Faturam entegratörü.
 *
 * Akış: sign-in ile alınan token (`x-access-token` header'ında döner) cache'lenir →
 * alıcının VKN/TCKN'si mükellef sorgusundan geçirilir → kayıtlıysa e-Fatura, değilse
 * e-Arşiv olarak gönderilir. Tutarlar API'ye KURUŞ (integer) gider (114.55 TL → 11455).
 *
 * Belge tipi (e-Fatura / e-Arşiv) `efatura_raw_response.document_type` içine yazılır;
 * checkStatus/cancel doğru endpoint'i buradan seçer.
 *
 * Statü kodları (Trendyol): 205 = Onaylandı (nihai başarı); 29/105/405 = hata;
 * 305 = iptal; gerisi işlemde. Bkz. docs/TRENDYOL_EFATURAM_API.md.
 */
class TrendyolEFaturamProvider implements EInvoiceProviderInterface
{
    public const DOC_EINVOICE = 'einvoice';
    public const DOC_EARCHIVE = 'earchive';

    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function send(OutgoingInvoice $invoice): EInvoiceSendResult
    {
        $taxId       = $this->normalizeTaxId($invoice->buyer_tax_number);
        $isEInvoice  = $taxId !== null && $this->isRegisteredTaxpayer($taxId);
        $documentType = $isEInvoice ? self::DOC_EINVOICE : self::DOC_EARCHIVE;

        $path        = $isEInvoice
            ? '/api/invoice/documents/outgoing-einvoice'
            : '/api/invoice/documents/earchive';
        $invoiceType = $isEInvoice
            ? ($this->config['einvoice_type'] ?? 'EFATURA')
            : ($this->config['earchive_type'] ?? 'EARSIVFATURA');

        $payload  = $this->buildPayload($invoice, $invoiceType);
        $response = $this->client()->post($path, $payload);

        if (! $response->successful()) {
            Log::warning('Trendyol e-Fatura gönderimi başarısız.', [
                'invoice_id' => $invoice->id,
                'http'       => $response->status(),
                'body'       => $response->json() ?? $response->body(),
            ]);

            return new EInvoiceSendResult(
                uuid: null,
                status: OutgoingInvoice::EFATURA_FAILED,
                rawResponse: ['document_type' => $documentType, 'error' => $response->json() ?? $response->body()],
            );
        }

        $body = $response->json();

        return new EInvoiceSendResult(
            uuid: $body['invoiceUuid'] ?? null,
            status: $this->mapStatus($body['status'] ?? null),
            rawResponse: ['document_type' => $documentType] + (is_array($body) ? $body : []),
        );
    }

    public function checkStatus(OutgoingInvoice $invoice): EInvoiceStatusResult
    {
        if (! $invoice->efatura_uuid) {
            return new EInvoiceStatusResult(status: OutgoingInvoice::EFATURA_NOT_SENT);
        }

        $documentType = $invoice->efatura_raw_response['document_type'] ?? self::DOC_EARCHIVE;
        $path = $documentType === self::DOC_EINVOICE
            ? "/api/invoice/documents/outgoing-einvoice/status/{$invoice->efatura_uuid}"
            : "/api/invoice/documents/earchive/status/{$invoice->efatura_uuid}";

        $response = $this->client()->get($path);

        if (! $response->successful()) {
            return new EInvoiceStatusResult(
                status: OutgoingInvoice::EFATURA_PENDING,
                rawResponse: ['error' => $response->json() ?? $response->body()],
            );
        }

        $body = $response->json();

        return new EInvoiceStatusResult(
            status: $this->mapStatus($body['status'] ?? null),
            rawResponse: is_array($body) ? $body : null,
        );
    }

    public function cancel(OutgoingInvoice $invoice): bool
    {
        $documentType = $invoice->efatura_raw_response['document_type'] ?? null;

        // API üzerinden yalnızca e-Arşiv iptal edilebilir. e-Fatura iptali karşı tarafın
        // reddi / GİB iptal senaryosu gerektirir; entegratör tekil bir iptal ucu sunmuyor.
        if ($documentType !== self::DOC_EARCHIVE || ! $invoice->efatura_uuid) {
            Log::info('Trendyol iptal atlandı (e-Fatura API iptali desteklenmiyor veya uuid yok).', [
                'invoice_id'    => $invoice->id,
                'document_type' => $documentType,
            ]);

            return false;
        }

        $response = $this->client()->post('/api/invoice/documents/earchive/cancel', [
            'invoiceUuid' => $invoice->efatura_uuid,
            'companyId'   => (int) ($this->config['company_id'] ?? 0),
        ]);

        return $response->successful();
    }

    /**
     * VKN/TCKN'nin GİB e-Fatura mükellefi olup olmadığını sorgular.
     * 200 + boş olmayan dizi → kayıtlı; 404 / boş → kayıtlı değil (e-Arşiv).
     */
    private function isRegisteredTaxpayer(string $taxId): bool
    {
        $response = $this->client()->get("/api/invoice/taxpayers/{$taxId}");

        if ($response->status() === 404 || ! $response->successful()) {
            return false;
        }

        $body = $response->json();

        return is_array($body) && count($body) > 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(OutgoingInvoice $invoice, string $invoiceType): array
    {
        $payload = [
            'source'        => $this->config['source'] ?? 'PORTAL',
            'recipientInfo' => array_filter([
                'taxId'       => $this->normalizeTaxId($invoice->buyer_tax_number),
                'countryCode' => 'TR',
                'address'     => $invoice->buyer_address,
                'name'        => $invoice->buyer_name,
            ], static fn ($v) => $v !== null && $v !== ''),
            'currencyInfo'  => ['currency' => $invoice->currency ?: 'TRY'],
            'invoiceInfo'   => [
                'invoiceType'     => $invoiceType,
                'invoiceTypeCode' => $this->config['invoice_type_code'] ?? 'SATIS',
            ],
            'invoiceLines'  => $this->buildLines($invoice),
        ];

        if (isset($this->config['company_id']) && $this->config['company_id'] !== null) {
            $payload['companyId'] = (int) $this->config['company_id'];
        }

        return $payload;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildLines(OutgoingInvoice $invoice): array
    {
        $invoice->loadMissing('items');

        if ($invoice->items->isNotEmpty()) {
            return $invoice->items->map(fn ($item) => $this->line(
                itemName: $item->item_name,
                quantity: (float) $item->quantity,
                unitPrice: (float) $item->unit_price,
                taxable: (float) $item->taxable_amount,
                vatRate: (float) $item->vat_rate,
                vatAmount: (float) $item->vat_amount,
                lineTotal: (float) $item->line_total,
                unitCode: $item->unit_code ?: 'C62',
            ))->all();
        }

        // Kalem yoksa (order/tenant kaynaklı fatura) toplamlardan tek özet satır üret.
        $subtotal = (float) $invoice->subtotal;
        $rate     = $subtotal > 0 ? round((float) $invoice->tax_amount / $subtotal * 100, 2) : 0.0;

        return [$this->line(
            itemName: $invoice->note ?: 'Mal / Hizmet',
            quantity: 1,
            unitPrice: $subtotal,
            taxable: $subtotal,
            vatRate: $rate,
            vatAmount: (float) $invoice->tax_amount,
            lineTotal: (float) $invoice->total,
            unitCode: 'C62',
        )];
    }

    /**
     * @return array<string,mixed>
     */
    private function line(
        string $itemName,
        float $quantity,
        float $unitPrice,
        float $taxable,
        float $vatRate,
        float $vatAmount,
        float $lineTotal,
        string $unitCode,
    ): array {
        return [
            'unitCode'        => $unitCode,
            'quantity'        => $quantity,
            'itemName'        => $itemName,
            'unitPriceAmount' => $this->kurus($unitPrice),
            'taxableAmount'   => $this->kurus($taxable),
            'taxPercent'      => $vatRate,
            'taxAmount'       => $this->kurus($vatAmount),
            'totalAmount'     => $this->kurus($lineTotal),
            'totalTax'        => [
                'totalTaxAmount' => $this->kurus($vatAmount),
                'subTotalTaxes'  => [[
                    'taxableAmount' => $this->kurus($taxable),
                    'taxAmount'     => $this->kurus($vatAmount),
                    'taxType'       => 'KDV',
                    'percent'       => $vatRate,
                    'name'          => 'KDV',
                ]],
            ],
        ];
    }

    /** TL tutarını kuruşa (integer) çevirir: 114.55 → 11455. */
    private function kurus(float $tl): int
    {
        return (int) round($tl * 100);
    }

    private function normalizeTaxId(?string $taxNumber): ?string
    {
        if ($taxNumber === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $taxNumber);

        return $digits === '' ? null : $digits;
    }

    /**
     * Trendyol statü kodunu bizim efatura_status enum'una eşler.
     * 205 = Onaylandı (nihai) → success; 29/105/405 = hata, 305 = iptal → failed;
     * gerisi (10/20/30/40/50/100/200) işlemde → pending.
     */
    private function mapStatus(int|string|null $code): string
    {
        return match ((int) $code) {
            205               => OutgoingInvoice::EFATURA_SUCCESS,
            29, 105, 305, 405 => OutgoingInvoice::EFATURA_FAILED,
            0                 => OutgoingInvoice::EFATURA_PENDING,
            default           => OutgoingInvoice::EFATURA_PENDING,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['x-access-token' => $this->token()]);
    }

    /**
     * Sign-in ile token alır (response header `x-access-token`), cache'ler.
     */
    private function token(): string
    {
        return Cache::remember(
            'finance.einvoice.trendyol.token',
            (int) ($this->config['token_ttl'] ?? 3000),
            function (): string {
                $response = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
                    ->acceptJson()
                    ->asJson()
                    ->post('/api/auth/signin', [
                        'email'    => $this->config['email'],
                        'password' => $this->config['password'],
                    ]);

                $token = $response->header('x-access-token');

                if (! $response->successful() || $token === '') {
                    throw new RuntimeException('Trendyol e-Faturam sign-in başarısız: HTTP ' . $response->status());
                }

                return $token;
            },
        );
    }
}
